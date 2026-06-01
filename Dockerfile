# Build the frontend assets with Node
FROM node:20-alpine AS asset-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install --legacy-peer-deps
COPY . .
RUN npm install --prefix public/ws-server --legacy-peer-deps
RUN npm run build

# Build the PHP application image with nginx + php-fpm
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    curl \
    git \
    zip \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    nginx \
    supervisor \
  && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs \
  && docker-php-ext-install pdo_mysql mbstring zip intl sockets \
  && rm -rf /var/lib/apt/lists/*

# Configure PHP-FPM to run as www-data
RUN sed -i 's/user = .*/user = www-data/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/group = .*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/;pm.max_children = .*/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/;pm.start_servers = .*/pm.start_servers = 5/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/;pm.min_spare_servers = .*/pm.min_spare_servers = 2/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/;pm.max_spare_servers = .*/pm.max_spare_servers = 10/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
COPY --from=asset-builder /app /var/www/html

# Install Composer and dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer install --no-dev --optimize-autoloader --no-interaction \
  && rm -rf /root/.composer/cache

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Copy startup script
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Create nginx cache/pid directories
RUN mkdir -p /var/run/nginx /var/cache/nginx

# Set production environment defaults (Railway can override these)
ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 80 3000
ENTRYPOINT ["/docker-entrypoint.sh"]
