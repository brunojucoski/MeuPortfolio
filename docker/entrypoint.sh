#!/bin/sh
set -eu
umask 077

mkdir -p "$LARAVEL_STORAGE_PATH/framework/cache/data" \
    "$LARAVEL_STORAGE_PATH/framework/sessions" "$LARAVEL_STORAGE_PATH/framework/views" \
    "$LARAVEL_STORAGE_PATH/logs" "$LARAVEL_STORAGE_PATH/app/private" \
    "$LARAVEL_STORAGE_PATH/app/public" /tmp/meuportfolio-bootstrap
for file in /app/bootstrap/cache/*.php; do
    [ ! -f "$file" ] || cp "$file" /tmp/meuportfolio-bootstrap/
done

if [ "${1:-}" != "frankenphp" ]; then
    exec "$@"
fi

# Check configuration without opening remote connections or changing the database.
php artisan config:cache --no-interaction
php artisan deploy:check --startup --no-interaction
php artisan view:cache --no-interaction
exec "$@"
