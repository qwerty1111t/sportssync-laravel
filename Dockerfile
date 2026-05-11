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
    npm

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

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Create startup script
RUN echo '#!/bin/bash\n\
set -e\n\
export PORT=${PORT:-8000}\n\
echo "Starting Laravel application on port $PORT"\n\
php artisan optimize:clear\n\
php artisan migrate --force\n\
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf' > /usr/local/bin/startup.sh \
    && chmod +x /usr/local/bin/startup.sh

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE ${PORT:-8000}

# Health check
HEALTHCHECK --interval=10s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8000}/ || exit 1

# Start services
CMD ["/usr/local/bin/startup.sh"]