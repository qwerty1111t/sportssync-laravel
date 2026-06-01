#!/bin/bash

echo "Starting Container"
echo "Starting PHP-FPM and Nginx..."

# Initialize Laravel before starting services
cd /var/www/html

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Map Railway's MYSQL_* variables to Laravel's DB_* variables
# Railway provides: MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLPORT, MYSQL_DATABASE
if [ ! -z "$MYSQLHOST" ]; then
    echo "Mapping Railway MySQL variables to Laravel DB_* format..."
    export DB_CONNECTION=${DB_CONNECTION:-mysql}
    export DB_HOST=${MYSQLHOST}
    export DB_PORT=${MYSQLPORT:-3306}
    export DB_DATABASE=${MYSQL_DATABASE}
    export DB_USERNAME=${MYSQLUSER}
    export DB_PASSWORD=${MYSQLPASSWORD}
fi

# Set production environment defaults (only if not already set)
export APP_ENV=${APP_ENV:-production}
export APP_DEBUG=${APP_DEBUG:-false}

# Verify critical environment variables are set
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY not set in Railway environment variables!"
    exit 1
fi

echo "Starting with:"
echo "  APP_ENV=$APP_ENV"
echo "  APP_DEBUG=$APP_DEBUG"
echo "  DB_CONNECTION=$DB_CONNECTION"
echo "  DB_HOST=$DB_HOST"
echo "  DB_PORT=$DB_PORT"
echo "  DB_DATABASE=$DB_DATABASE"
echo "  DB_USERNAME=$DB_USERNAME"

# Run migrations with detailed output
echo "Running Laravel migrations..."
php artisan migrate --force

# Clear all caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Optimize for production
echo "Optimizing for production..."
php artisan optimize

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create supervisor config
cat > /etc/supervisor/conf.d/services.conf << 'EOF'
[supervisord]
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
user=root

[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/php-fpm.err.log
stdout_logfile=/var/log/supervisor/php-fpm.out.log

[program:nodejs]
command=bash -c "cd /var/www/html/public/ws-server && npm start"
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/nodejs.err.log
stdout_logfile=/var/log/supervisor/nodejs.out.log

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/nginx.err.log
stdout_logfile=/var/log/supervisor/nginx.out.log
EOF

mkdir -p /var/log/supervisor

# Start supervisord in foreground with nodaemon (-n) flag
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf -n
