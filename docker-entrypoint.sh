#!/bin/bash
set -e

echo "Starting PHP-FPM and Nginx..."

# Start PHP-FPM in the background
php-fpm &
PHP_FPM_PID=$!

# Start Node.js WebSocket server in the background
cd /var/www/html/public/ws-server && npm start &
WS_PID=$!

# Start nginx in the foreground
exec nginx -g "daemon off;"
