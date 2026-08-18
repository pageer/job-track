#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for the database to become available..."
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Warming up the cache..."
php bin/console cache:warmup --no-interaction

if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
  if php bin/console app:create-admin "$ADMIN_EMAIL" "$ADMIN_NAME" "$ADMIN_PASSWORD" >/dev/null 2>&1; then
    echo "Created admin user: $ADMIN_EMAIL"
  else
    echo "Admin user already exists or could not be created (skipping)."
  fi
fi

echo "Starting Apache..."
exec "$@"
