# Build the frontend assets with Node
FROM node:20-bullseye-slim AS asset-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install --legacy-peer-deps
COPY . .
RUN npm install --prefix public/ws-server --legacy-peer-deps
RUN npm run build

# Build the PHP application image with Nginx + Supervisor
FROM php:8.2-fpm-bullseye

RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    git \
    nginx \
    supervisor \
    zip \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
  && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs \
  && docker-php-ext-install pdo_mysql mbstring zip intl sockets \
  && sed -i 's@^listen = .*@listen = 127.0.0.1:9000@' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's@^user = .*@user = www-data@' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's@^group = .*@group = www-data@' /usr/local/etc/php-fpm.d/www.conf \
  && printf '\nlisten.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660\n' >> /usr/local/etc/php-fpm.d/www.conf \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=asset-builder /app /var/www/html
COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer install --no-dev --optimize-autoloader --no-interaction \
  && rm -rf /root/.composer/cache

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 3000
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
