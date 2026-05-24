FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor \
    nodejs \
    npm \
    nginx

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies
RUN npm install

# Build frontend assets
RUN npm run build

# Install WebSocket server dependencies
RUN cd public/ws-server && npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Create startup script that exports environment variables globally
RUN echo '#!/bin/bash\n\
set -e\n\
# Map Railway MySQL service variables to application DB_* variables\n\
export DB_HOST=${DB_HOST:-${MYSQLHOST:-localhost}}\n\
export DB_PORT=${DB_PORT:-${MYSQLPORT:-3306}}\n\
export DB_DATABASE=${DB_DATABASE:-${MYSQLDATABASE:-railway}}\n\
export DB_USERNAME=${DB_USERNAME:-${MYSQLUSER:-root}}\n\
export DB_PASSWORD=${DB_PASSWORD:-${MYSQLPASSWORD:-}}\n\
export DB_CONNECTION=${DB_CONNECTION:-mysql}\n\
export PORT=${PORT:-8080}\n\
export WS_PORT=${WS_PORT:-3000}\n\
export APP_ENV=${APP_ENV:-production}\n\
export APP_DEBUG=${APP_DEBUG:-false}\n\
# Log the resolved database connection\n\
echo "Database config: DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_DATABASE=$DB_DATABASE DB_USERNAME=$DB_USERNAME"\n\
echo "Starting Laravel application on port $PORT with WebSocket on port $WS_PORT"\n\
php artisan optimize:clear\n\
php artisan migrate --force\n\
# Start supervisord with the exported environment\n\
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf' > /usr/local/bin/startup.sh \
    && chmod +x /usr/local/bin/startup.sh

# Copy supervisor and Nginx configs
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY nginx.conf /etc/nginx/nginx.conf

# Expose ports
EXPOSE 8080 3000

# Health check
HEALTHCHECK --interval=10s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Start services
CMD ["/usr/local/bin/startup.sh"]