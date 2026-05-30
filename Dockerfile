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

RUN apt-get update && apt-get install -y --no-install-recommends \
  ca-certificates \
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
  libsqlite3-dev \
  pkg-config \
  && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y --no-install-recommends nodejs \
  && docker-php-ext-install pdo_mysql pdo_sqlite mbstring zip intl sockets \
  && sed -i 's@^listen = .*@listen = 127.0.0.1:9000@' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's@^user = .*@user = www-data@' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's@^group = .*@group = www-data@' /usr/local/etc/php-fpm.d/www.conf \
  && grep "^listen\|^user\|^group" /usr/local/etc/php-fpm.d/www.conf \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www/html
COPY --from=asset-builder /app /var/www/html
COPY nginx.conf /etc/nginx/nginx.conf.template
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY scripts/start-nginx.sh /usr/local/bin/start-nginx.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
  && chmod +x /usr/local/bin/start-nginx.sh \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer install --no-dev --optimize-autoloader --no-interaction --verbose \
  && [ -f vendor/autoload.php ] || (echo "ERROR: Composer install failed - vendor/autoload.php not found"; exit 1) \
  && rm -rf /root/.composer/cache \
  && mkdir -p /var/run/php \
  && chown -R www-data:www-data /var/run/php /var/www/html/storage /var/www/html/bootstrap/cache \
  && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000 3000
CMD ["/usr/local/bin/entrypoint.sh"]
