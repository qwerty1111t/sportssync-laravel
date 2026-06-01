#!/usr/bin/env bash
set -e

# Ensure values from Railway env do not keep surrounding quotes
strip_quotes() {
  local v="$1"
  v="${v#\"}"
  v="${v%\"}"
  printf '%s' "$v"
}

PORT=$(strip_quotes "${PORT:-8000}")
WS_PORT=$(strip_quotes "${WS_PORT:-3000}")
APP_KEY=$(strip_quotes "${APP_KEY:-}")
APP_ENV=$(strip_quotes "${APP_ENV:-}")
APP_URL=$(strip_quotes "${APP_URL:-}")

# Generate nginx config from template using runtime PORT
if [ -f /etc/nginx/nginx.conf.template ]; then
  sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
  echo "[Init] Nginx config generated with PORT=${PORT}"
fi

cd /var/www/html

if [ -n "$DB_HOST" ]; then
  echo "[Init] Testing MySQL connection to $DB_HOST:$DB_PORT..."
  php -r "
    try {
      \$pdo = new PDO('mysql:host='.\$_ENV['DB_HOST'].',port='.\$_ENV['DB_PORT'].';dbname='.\$_ENV['DB_DATABASE'], \$_ENV['DB_USERNAME'], \$_ENV['DB_PASSWORD']);
      echo '[Init] ✓ MySQL connection successful' . PHP_EOL;
    } catch (Exception \$e) {
      echo '[Init] ✗ MySQL connection failed: ' . \$e->getMessage() . PHP_EOL;
      exit(1);
    }
  " || exit 1
fi

# Run migrations
echo "[Init] Running database migrations..."
php artisan migrate --force || true

# Seed superadmin if needed
echo "[Init] Seeding superadmin user..."
php artisan db:seed --class=SuperadminSeeder || true

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
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

# Startup diagnostics - COMPREHENSIVE
echo ""
echo "========================================"
echo "      STARTUP DIAGNOSTICS - FULL"
echo "========================================"
echo ""

# 1. Print Generated Nginx Config
echo "[1] GENERATED NGINX CONFIGURATION:"
if [ -f /etc/nginx/nginx.conf ]; then
  cat /etc/nginx/nginx.conf
else
  echo "ERROR: /etc/nginx/nginx.conf NOT FOUND"
fi
echo ""

# 2. Test Nginx Configuration
echo "[2] NGINX CONFIGURATION TEST (nginx -T):"
/usr/sbin/nginx -t 2>&1 || echo "NGINX TEST FAILED"
echo ""

# 3. Show PHP-FPM Config
echo "[3] PHP-FPM CONFIGURATION:"
echo "Active listen setting:"
grep "^listen" /usr/local/etc/php-fpm.d/www.conf || echo "  (not found)"
echo ""

# 4. Test PHP-FPM Configuration
echo "[4] PHP-FPM CONFIGURATION TEST (php-fpm -tt):"
/usr/local/sbin/php-fpm -tt 2>&1 || echo "PHP-FPM TEST FAILED"
echo ""

# 5. Verify File Existence
echo "[5] FILE EXISTENCE CHECKS:"
echo "Laravel root: $(pwd)"
echo "  public/index.php: $([ -f public/index.php ] && echo 'EXISTS' || echo 'MISSING')"
echo "  storage: $([ -d storage ] && echo 'EXISTS' || echo 'MISSING')"
echo "  bootstrap/cache: $([ -d bootstrap/cache ] && echo 'EXISTS' || echo 'MISSING')"
echo "  .env: $([ -f .env ] && echo 'EXISTS' || echo 'MISSING')"
echo "  artisan: $([ -f artisan ] && echo 'EXISTS' || echo 'MISSING')"
echo ""

# 6. Verify Permissions
echo "[6] PERMISSIONS AND OWNERSHIP:"
ls -ld storage bootstrap/cache
ls -l public/index.php
echo ""

# 7. Network Listening Ports (after startup)
echo "[7] CONFIGURED PORTS:"
echo "  PORT=${PORT}"
echo "  WS_PORT=${WS_PORT}"
echo ""

# 8. Laravel .env Status
echo "[8] LARAVEL ENVIRONMENT (.env) - FIRST 30 LINES:"
if [ -f .env ]; then
    head -30 .env || echo "  (read failed)"
    echo ""
    echo "  APP_KEY line:"
    grep "^APP_KEY" .env || echo "    (APP_KEY not found)"
    echo "  APP_DEBUG line:"
    grep "^APP_DEBUG" .env || echo "    (APP_DEBUG not found)"
    echo "  APP_ENV line:"
    grep "^APP_ENV" .env || echo "    (APP_ENV not found)"
else
    echo "  (no .env file)"
fi
echo ""

# 9. Check PHP Extensions
echo "[9] PHP EXTENSIONS LOADED:"
php -m | grep -E "PDO|Core|json|filter" || echo "  (checking failed)"
echo ""

# 10. Check Composer Installation Status
echo "[10] COMPOSER/VENDOR STATUS:"
if [ -d vendor ]; then
    echo "  vendor/ directory EXISTS"
    vendor_count=$(find vendor -type f -name "*.php" | wc -l)
    echo "  PHP files in vendor: $vendor_count"
    if [ -f vendor/autoload.php ]; then
        echo "  ✓ vendor/autoload.php EXISTS"
    else
        echo "  ✗ vendor/autoload.php MISSING"
    fi
    if [ -d vendor/laravel ]; then
        echo "  ✓ vendor/laravel/ EXISTS"
    else
        echo "  ✗ vendor/laravel/ MISSING"
    fi
else
    echo "  ✗ vendor/ directory MISSING"
fi
echo ""

# 11. Try PHP to autoload
echo "[11] AUTOLOADER TEST:"
php -r "require 'vendor/autoload.php'; echo 'Autoloader loaded successfully\n';" 2>&1 || echo "  (autoloader test failed)"
echo ""

# 12. Bootstrap Test (before Laravel request)
echo "[12] LARAVEL BOOTSTRAP TEST:"
php -r "
\$app = require 'bootstrap/app.php';
echo 'Bootstrap loaded successfully\n';
echo 'App class: ' . get_class(\$app) . '\n';
" 2>&1 || echo "  (bootstrap test failed)"
echo ""

# 10. FastCGI Connection Test
echo "[13] FASTCGI CONNECTION TEST:"
(echo -n 'GET / HTTP/1.0\r\n\r\n'; sleep 1) | nc 127.0.0.1 9000 2>&1 | head -20 || echo "  (netcat failed or no response)"
echo ""

echo "========================================"
echo "   END STARTUP DIAGNOSTICS"
echo "========================================"
echo ""

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf

