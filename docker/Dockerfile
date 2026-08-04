# syntax=docker/dockerfile:1

# Single container: nginx (static + PHP routing) + php-fpm, based on Laravel 9
# (PHP ^8.0.2). Listens on port 80; php-fpm listens on 127.0.0.1:9000.
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
        nginx \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        oniguruma-dev \
        icu-dev \
        bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring gd bcmath exif zip intl opcache fileinfo \
    && rm -rf /var/cache/apk/*

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies before copying the rest of the source (layer caching).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-interaction --prefer-dist

COPY . .

# Dependencies verify + clean temp/node_modules (rebuilt inside the container).
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf \
        storage/logs/* \
        storage/framework/cache/* \
        storage/framework/sessions/* \
        storage/framework/views/* \
        public/stisla/node_modules \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

# Nginx + php-fpm runtime configuration.
COPY docker/nginx/backend.conf /etc/nginx/http.d/default.conf
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-mzt.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]