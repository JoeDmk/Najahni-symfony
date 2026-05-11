#!/bin/bash
set -e

# Render sets PORT env var — configure Apache to listen on it
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/*.conf
fi

# Append serverVersion to DATABASE_URL for PostgreSQL on Render
if echo "$DATABASE_URL" | grep -q "^postgres"; then
    if ! echo "$DATABASE_URL" | grep -q "serverVersion"; then
        if echo "$DATABASE_URL" | grep -q "?"; then
            export DATABASE_URL="${DATABASE_URL}&serverVersion=16"
        else
            export DATABASE_URL="${DATABASE_URL}?serverVersion=16"
        fi
    fi
fi

echo "=== DATABASE_URL=${DATABASE_URL:0:30}... ==="
echo "=== APP_ENV=$APP_ENV ==="

# Create database schema from entities (skip migrations — they contain MySQL-specific SQL)
echo "=== Dropping existing schema (fresh deploy) ==="
php bin/console doctrine:schema:drop --force --full-database --no-interaction 2>&1 || true
echo "=== Creating schema ==="
php bin/console doctrine:schema:create --no-interaction 2>&1 || echo "WARN: schema create failed"

# Warm up Symfony cache
echo "=== Warming up cache ==="
php bin/console cache:warmup --env=prod --no-debug 2>&1 || echo "WARN: cache warmup failed"

# Fix permissions after cache warmup
chown -R www-data:www-data var/

echo "=== Starting Apache ==="
exec apache2-foreground
