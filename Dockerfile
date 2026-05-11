# ── Stage 1: Composer dependencies ─────────────────────────────
FROM composer:2 AS composer_stage

WORKDIR /app
COPY composer.json composer.lock ./

# Install system deps needed by PHP extensions during composer install
RUN apk add --no-cache icu-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        freetype-dev oniguruma-dev libxml2-dev postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) intl pdo pdo_mysql pdo_pgsql zip gd mbstring xml

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
 && composer dump-env prod \
 && php -r "\$f='.env.local.php';\$e=require \$f;file_put_contents(\$f,'<?php return '.var_export(['APP_ENV'=>'prod','APP_DEBUG'=>'0'],true).';'.chr(10));" \
 && composer run-script post-install-cmd --no-interaction 2>/dev/null || true

# Compile AssetMapper assets for production
RUN php bin/console asset-map:compile --env=prod 2>/dev/null || true

# ── Stage 2: Production image ─────────────────────────────────
FROM php:8.2-apache

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev libxml2-dev libpq-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl pdo pdo_mysql pdo_pgsql zip gd opcache mbstring xml \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache config: enable mod_rewrite, set document root
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    FallbackResource /index.php\n\
</Directory>' > /etc/apache2/conf-available/symfony.conf \
 && a2enconf symfony

# PHP production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/app.ini"

# Copy app from composer stage
WORKDIR /var/www/html
COPY --from=composer_stage /app .

# Force production mode
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Ensure upload dirs exist and are writable
RUN mkdir -p var/cache var/log public/uploads/profiles public/uploads/community/posts var/uploads/documents \
 && chown -R www-data:www-data var public/uploads var/uploads

# Expose port (Render uses $PORT)
EXPOSE 80

# Start script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
