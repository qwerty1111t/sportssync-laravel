#!/usr/bin/env bash
set -e

PORT=${PORT:-8000}
WS_PORT=${WS_PORT:-3000}

# Generate nginx config from template using runtime PORT
if [ -f /etc/nginx/nginx.conf.template ]; then
  sed "s/{{PORT}}/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
fi

cd /var/www/html
if [ -f artisan ]; then
  php artisan config:clear || true
  php artisan cache:clear || true
  php artisan route:clear || true
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
