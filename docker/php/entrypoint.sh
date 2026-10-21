#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

composer install --no-interaction --prefer-dist

php scripts/ensure-app-key.php

php artisan config:clear
php artisan migrate --force --no-interaction

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

exec docker-php-entrypoint "$@"
