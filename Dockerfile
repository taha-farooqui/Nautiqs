# --------------------------------------------------------------------------
# Stage 1: build frontend assets with Node
# --------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# --------------------------------------------------------------------------
# Stage 2: PHP runtime with the mongodb extension and Composer deps
# --------------------------------------------------------------------------
FROM php:8.2-cli-alpine AS app

# System deps + the mongodb PHP extension.
# `ca-certificates` is required for TLS to MongoDB Atlas — Alpine doesn't
# ship the full root CA bundle by default and Atlas's TLS handshake fails
# without it.
RUN apk add --no-cache \
        ca-certificates \
        git unzip libzip-dev openssl-dev curl-dev oniguruma-dev icu-dev libpng-dev \
        $PHPIZE_DEPS \
    && update-ca-certificates \
    && docker-php-ext-install zip intl mbstring bcmath \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/* /var/cache/apk/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first (better caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# Copy app source + built frontend assets
COPY . .
COPY --from=assets /app/public/build /app/public/build

# Run scripts now that the full source is in place
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true

# Permissions for Laravel writable paths
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

# Default port — Railway sets $PORT at runtime
ENV PORT=8080
EXPOSE 8080

# Cache config + routes + views, then start PHP's built-in server using
# Laravel's official router script (the same one `php artisan serve` uses).
# This lets static files in /public — including /build/* CSS/JS — be served
# directly without going through the Laravel router (which would 404 them
# or redirect them to /login).
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && cd public \
    && php -S 0.0.0.0:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
