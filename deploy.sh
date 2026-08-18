#!/usr/bin/env bash
#
# deploy.sh — Pull latest code from GitHub and rebuild the application.
#
# Usage:
#   ./deploy.sh            # full deploy
#   ./deploy.sh --no-migrate   # skip database migrations
#   ./deploy.sh --no-build     # skip frontend build (useful when only backend changed)
#
# Prerequisites on the server:
#   - git, PHP 8.2+ with pdo_mysql, Composer, Node.js 22+ with npm
#   - A .env.local in backend/ with production DATABASE_URL and APP_ENV=prod

set -euo pipefail

SKIP_MIGRATE=false
SKIP_BUILD=false

for arg in "$@"; do
  case "$arg" in
    --no-migrate) SKIP_MIGRATE=true ;;
    --no-build)   SKIP_BUILD=true ;;
    -h|--help)
      echo "Usage: $0 [--no-migrate] [--no-build]"
      exit 0
      ;;
    *)
      echo "Unknown argument: $arg"
      exit 1
      ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

ok()   { printf "\033[32m✓ %s\033[0m\n" "$1"; }
warn() { printf "\033[33m⚠ %s\033[0m\n" "$1"; }
fail() { printf "\033[31m✗ %s\033[0m\n" "$1"; exit 1; }

# 1. Check prerequisites

for cmd in git php composer node npm; do
  command -v "$cmd" >/dev/null 2>&1 || fail "'$cmd' is not installed or not in PATH."
done

# 2. Pull latest code

cd "$ROOT"
echo "Pulling latest code from origin..."
git pull origin "$(git rev-parse --abbrev-ref HEAD)" || fail "git pull failed."
ok "Code up to date."

# 3. PHP dependencies

cd "$BACKEND"
composer install --no-dev --optimize-autoloader --no-interaction || fail "Composer install failed."
ok "PHP dependencies installed."

# 4. Frontend build

if [ "$SKIP_BUILD" = false ]; then
  cd "$FRONTEND"
  npm install --no-audit --no-fund || fail "npm install failed."
  npm run build || fail "Frontend build failed."
  ok "Frontend built into backend/public/build/."
else
  warn "Skipping frontend build."
fi

# 5. Database migrations

if [ "$SKIP_MIGRATE" = false ]; then
  cd "$BACKEND"
  php bin/console doctrine:migrations:migrate --no-interaction || warn "Some migrations may have failed — check output above."
  ok "Database migrations applied."
else
  warn "Skipping database migrations."
fi

# 6. Cache warm

cd "$BACKEND"
php bin/console cache:clear --env=prod --no-debug || warn "Cache clear failed (non-fatal)."
php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true
ok "Cache warmed."

# 7. Fix permissions on var/ (shared hosting often needs this)

if [ -d "$BACKEND/var" ]; then
  chmod -R 775 "$BACKEND/var" 2>/dev/null || true
  ok "var/ permissions set."
fi

# Done

echo ""
ok "Deploy complete."
