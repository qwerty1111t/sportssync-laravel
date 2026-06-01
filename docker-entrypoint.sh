#!/bin/bash

echo "Starting Container"
echo "Starting PHP-FPM and Nginx..."

# Initialize Laravel before starting services
cd /var/www/html

# Create .env from .env.example if it doesn't exist (APP_KEY will be read from Railway env vars)
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Run migrations and clear cache
echo "Running Laravel migrations..."
php artisan migrate --force 2>/dev/null || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear

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
