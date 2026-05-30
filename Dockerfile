FROM node:18 AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --production=false
COPY . .
RUN npm run build || true

FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || true
COPY . .

FROM php:8.2-apache
RUN apt-get update && apt-get install -y zlib1g-dev libzip-dev unzip git curl && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql zip
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf || true
WORKDIR /var/www/html
COPY --from=composer /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build
COPY .env.example /var/www/html/.env
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
EXPOSE 8080
CMD ["apache2-foreground"]
