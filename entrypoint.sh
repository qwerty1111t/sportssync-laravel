#!/usr/bin/env bash
set -e

PORT=${PORT:-8000}
WS_PORT=${WS_PORT:-3000}

# Generate nginx config from template using runtime PORT
if [ -f /etc/nginx/nginx.conf.template ]; then
  sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
  echo "[Init] Nginx config generated with PORT=${PORT}"
fi

cd /var/www/html

# Ensure values from Railway env do not keep surrounding quotes
strip_quotes() {
  local v="$1"
  v="${v#\"}"
  v="${v%\"}"
  printf '%s' "$v"
}
APP_KEY=$(strip_quotes "$APP_KEY")
APP_ENV=$(strip_quotes "$APP_ENV")
APP_URL=$(strip_quotes "$APP_URL")
PORT=$(strip_quotes "$PORT")

# Ensure php-fpm socket directory exists with proper permissions
mkdir -p /var/run/php
chown -R www-data:www-data /var/run/php || true
chmod 775 /var/run/php || true

# Ensure .env exists so artisan commands won't fail
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "[Init] Copied .env.example to .env"
  else
    echo "APP_NAME=${APP_NAME:-SportSync}" > .env
    echo "[Init] Created minimal .env"
  fi
fi

# Set APP_KEY from environment if provided, otherwise generate one
if [ -n "$APP_KEY" ]; then
  if grep -q '^APP_KEY=' .env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
  else
    echo "APP_KEY=${APP_KEY}" >> .env
  fi
  echo "[Init] APP_KEY set from environment"
else
  # Generate APP_KEY if empty in .env
  if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
    echo "[Init] Generating APP_KEY..."
    php artisan key:generate --force || true
  fi
fi

# Only initialize Laravel if artisan exists (skip for pure static/ws serving)
if [ -f artisan ]; then
  # Generate APP_KEY if missing (critical for Laravel)
  if [ -z "$APP_KEY" ] || grep -q "^APP_KEY=$" .env; then
    echo "[Init] Generating APP_KEY..."
    php artisan key:generate --force || true
  fi
  
  # Set production environment if not already set
  if [ -z "$APP_ENV" ] || [ "$APP_ENV" = "local" ]; then
    echo "[Init] Setting APP_ENV to production..."
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env || true
  fi
  
  # Clear all caches (ensure fresh config from env vars)
  php artisan config:clear || true
  php artisan cache:clear || true
  php artisan route:clear || true
  
  # Run database migrations if on Railway
  if [ -n "$RAILWAY_ENVIRONMENT_NAME" ]; then
    echo "[Init] Running migrations on Railway..."
    php artisan migrate --force --no-interaction || true
  fi
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
