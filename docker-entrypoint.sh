#!/bin/bash

echo "Starting Container"
echo "Starting PHP-FPM and Nginx..."

# Initialize Laravel before starting services
cd /var/www/html

# Map Railway's MYSQL_* variables to Laravel's DB_* variables FIRST (before creating .env)
# Use INTERNAL Railway domain (mysql.railway.internal) not the public proxy!
if [ ! -z "$MYSQLHOST" ]; then
    echo "Mapping Railway MySQL variables to Laravel DB_* format..."
    export DB_CONNECTION=mysql
    
    # Use internal domain (mysql.railway.internal:3306) NOT viaduct proxy
    # This is faster and more reliable within Railway's network
    export DB_HOST=mysql.railway.internal
    export DB_PORT=3306
    export DB_DATABASE=${MYSQL_DATABASE}
    export DB_USERNAME=${MYSQLUSER}
    export DB_PASSWORD=${MYSQLPASSWORD}
    
    echo "MySQL connection: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
fi

# Create .env file from environment variables (don't rely on .env.example in Docker)
if [ ! -f .env ]; then
    echo "Creating .env file from environment variables..."
    cat > .env << ENVEOF
APP_NAME=SportSync
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sportssync-laravel-production.up.railway.app

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

APP_KEY=${APP_KEY}

SESSION_DRIVER=cookie
SESSION_SECURE_COOKIES=true
LOG_CHANNEL=stack
CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=log
ENVEOF
    echo "Created .env file with database credentials:"
    echo "  DB_HOST=${DB_HOST}"
    echo "  DB_DATABASE=${DB_DATABASE}"
    echo "  DB_USERNAME=${DB_USERNAME}"
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
if ! php artisan migrate --force; then
    echo "WARNING: Migrations failed, but continuing startup..."
    echo "This might cause 502 errors if database tables don't exist"
fi

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
# Display error logs so we can see what's happening
echo "Starting supervisor (logging to console)..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf -n 2>&1 | tee /var/log/supervisor/output.log
