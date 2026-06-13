#!/bin/bash

echo "Starting Container"
echo "Starting PHP-FPM and Nginx..."

# Initialize Laravel before starting services
cd /var/www/html

# Map Railway's MYSQL_* variables to Laravel's DB_* variables FIRST (before creating .env)
# Use the actual Railway-injected variables, NOT hardcoded internal domain
if [ ! -z "$MYSQLHOST" ]; then
    echo "Mapping Railway MySQL variables to Laravel DB_* format..."
    export DB_CONNECTION=mysql
    export DB_HOST=${MYSQLHOST}
    export DB_PORT=${MYSQLPORT:-3306}
    export DB_DATABASE=${MYSQL_DATABASE}
    export DB_USERNAME=${MYSQLUSER}
    export DB_PASSWORD=${MYSQLPASSWORD}
    
    echo "MySQL connection: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
fi

# Create .env file from environment variables (don't rely on .env.example in Docker)
if [ ! -f .env ]; then
    echo "Creating complete .env file from environment variables..."
    
    # Determine APP_URL dynamically based on RAILWAY_PUBLIC_DOMAIN (set by Railway)
    # Fall back to localhost for local development
    APP_URL="https://${RAILWAY_PUBLIC_DOMAIN:-localhost:8000}"
    if [ -z "$RAILWAY_PUBLIC_DOMAIN" ]; then
        APP_URL="http://localhost"
    fi
    
    cat > .env << ENVEOF
APP_NAME=SportSync
APP_ENV=production
APP_DEBUG=false
APP_URL=${APP_URL}
APP_KEY=${APP_KEY}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=cookie
SESSION_SECURE_COOKIES=true
SESSION_DOMAIN=${RAILWAY_PUBLIC_DOMAIN:-.}
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/

WS_HOST=${RAILWAY_PUBLIC_DOMAIN:-127.0.0.1:3000}
WS_SCHEME=${RAILWAY_PUBLIC_DOMAIN:+wss}${RAILWAY_PUBLIC_DOMAIN:+:ws}
WS_ALLOWED_ORIGINS=${APP_URL}
WS_PORT=3000
WS_EMIT_URL=${APP_URL}/ws/emit

PORT=8000

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=rellevejoanner@gmail.com
MAIL_PASSWORD=woxjutnovhnkajbd
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=rellevejoanner@gmail.com
MAIL_FROM_NAME=Sportssync

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME=SportSync

SUPERADMIN_USERNAME=sportssyn@admin
SUPERADMIN_EMAIL=sportssyncsuper@gmail.com
SUPERADMIN_PASSWORD=BVBTDarts_super@123
SUPERADMIN_UPDATE_PASSWORD=false
ENVEOF
    echo "Created complete .env file with dynamic APP_URL: ${APP_URL}"
    echo "MySQL connection: ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
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
startsecs=10
startretries=5
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
