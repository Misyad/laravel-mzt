#!/bin/sh
set -e

cd /app

# Make the runtime dirs writable (especially the bind-mounted storage/app).
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data /app/storage/app 2>/dev/null || true

# Point public/storage at storage/app/public (idempotent).
[ -L public/storage ] || php artisan storage:link

# Discover service providers / package manifests without touching the DB.
php artisan package:discover --ansi || true

# php-fpm daemonises and nginx stays in the foreground so the container stays
# alive and Docker restart policies can supervise it.
php-fpm -D || true
exec nginx -g "daemon off;"