#!/bin/bash
set -e

# Render sets PORT env var — configure Apache to listen on it
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/*.conf
fi

echo "=== DATABASE_URL=${DATABASE_URL:0:30}... ==="
echo "=== APP_ENV=$APP_ENV ==="

# Create database schema from entities (skip migrations — they contain MySQL-specific SQL)
echo "=== Running doctrine:schema:update ==="
php bin/console doctrine:schema:update --force --no-interaction 2>&1 || {
    echo "WARN: schema update failed, attempting fresh schema..."
    php bin/console doctrine:schema:drop --force --no-interaction 2>&1 || true
    php bin/console doctrine:schema:update --force --no-interaction 2>&1 || echo "WARN: schema update failed again"
}

# Warm up Symfony cache
echo "=== Warming up cache ==="
php bin/console cache:warmup --env=prod --no-debug 2>&1 || echo "WARN: cache warmup failed"

# Fix permissions after cache warmup
chown -R www-data:www-data var/

echo "=== Starting Apache ==="
exec apache2-foreground
