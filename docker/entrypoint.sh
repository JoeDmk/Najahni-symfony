#!/bin/bash
set -e

# Render sets PORT env var — configure Apache to listen on it
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/*.conf
fi

# Create database schema from entities (skip migrations — they contain MySQL-specific SQL)
php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

# Warm up Symfony cache
php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true

# Fix permissions after cache warmup
chown -R www-data:www-data var/

# Start Apache
exec apache2-foreground
