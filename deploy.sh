#!/usr/bin/env bash
set -euo pipefail

DRY_RUN=false
SKIP_MIGRATIONS=false

while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=true; shift ;;
    --skip-migrations) SKIP_MIGRATIONS=true; shift ;;
    -h|--help)
      echo "Usage: $0 [--dry-run] [--skip-migrations]";
      exit 0;
      ;;
    *) echo "Unknown arg: $1"; exit 2 ;;
  esac
done

echo_color() { echo -e "\033[32m$1\033[0m"; }
echo_error() { echo -e "\033[31m$1\033[0m"; }

check_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo_error "Missing required command: $1"; exit 1; }
}

detect_env() {
  echo_color "Checking required CLI tools..."
  for cmd in git node npm composer railway; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
      echo_error "Command '$cmd' not found. Please install it and login (if applicable)."
      exit 1
    fi
  done
  echo_color "All required CLIs present."
}

ensure_env_file() {
  if [ ! -f .env ]; then
    if [ -f .env.example ]; then
      echo_color ".env not found — copying .env.example -> .env"
      if [ "$DRY_RUN" = false ]; then
        cp .env.example .env
        echo_color "Please review .env and update secrets before continuing (railway will use its own vars)."
      fi
    else
      echo_error "No .env or .env.example found. Create one and set production values."
      exit 1
    fi
  fi
}

install_deps() {
  echo_color "Installing PHP dependencies (composer)..."
  if [ "$DRY_RUN" = true ]; then echo "DRY-RUN: composer install --no-dev --optimize-autoloader"; else composer install --no-dev --no-interaction --optimize-autoloader; fi

  if [ -f package.json ]; then
    echo_color "Installing Node dependencies (npm)..."
    if [ "$DRY_RUN" = true ]; then echo "DRY-RUN: npm ci && npm run build"; else npm ci && npm run build; fi
  fi
}

run_migrations() {
  if [ "$SKIP_MIGRATIONS" = true ]; then echo_color "Skipping migrations per flag."; return; fi
  if [ "$DRY_RUN" = true ]; then echo "DRY-RUN: php artisan migrate --force"; else echo_color "Running database migrations..."; php artisan migrate --force; fi
}

deploy_railway() {
  echo_color "Starting Railway deployment (railway up)..."
  if [ "$DRY_RUN" = true ]; then echo "DRY-RUN: railway up"; else railway up; fi
}

read_app_url() {
  if grep -q '^APP_URL=' .env 2>/dev/null; then
    APP_URL=$(grep '^APP_URL=' .env | head -n1 | cut -d'=' -f2-)
  else
    APP_URL="http://localhost"
  fi
  APP_URL=${APP_URL:-http://localhost}
  echo "$APP_URL"
}

health_check() {
  URL=$(read_app_url)
  echo_color "Running health check against $URL"
  if [ "$DRY_RUN" = true ]; then echo "DRY-RUN: curl -fsS $URL/health || curl -fsS $URL"; return; fi
  for i in {1..12}; do
    if curl -fsS "$URL/health" >/dev/null 2>&1; then echo_color "✓ Health OK"; return 0; fi
    if curl -fsS "$URL" >/dev/null 2>&1; then echo_color "✓ Health OK (root)"; return 0; fi
    echo "Waiting for app to be healthy... ($i/12)"; sleep 5
  done
  echo_error "Health check failed after retries."; return 2
}

main() {
  detect_env
  ensure_env_file
  install_deps
  run_migrations
  deploy_railway
  health_check || echo_error "Post-deploy health check failed; check Railway logs."
  echo_color "Deployment script finished."
}

main
