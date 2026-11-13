#!/bin/sh
# Curbcut container entrypoint.
#   web       Apache + PHP (default). Runs database migrations first.
#   worker    Queue worker that runs scans.
#   scheduler Runs scheduled scans (daily / weekly sites).
#   anything else is run as a command, e.g. `php artisan curbcut:user you@example.com`.
set -e
cd /var/www/html

# Keep the app key with the data, so sessions survive container rebuilds.
if [ -z "$APP_KEY" ]; then
    if [ ! -s /data/app_key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app_key
        echo "Curbcut: generated a new APP_KEY in /data/app_key"
    fi
    APP_KEY="$(cat /data/app_key)"
    export APP_KEY
fi

if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi
fix_permissions() {
    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data /data storage bootstrap/cache
    fi
}

as_www() {
    if [ "$(id -u)" = "0" ]; then
        exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
    fi
    exec "$@"
}

prepare() {
    php artisan optimize --quiet
    fix_permissions
}

case "$1" in
    web)
        php artisan migrate --force --no-interaction
        prepare
        exec apache2-foreground
        ;;
    worker)
        prepare
        # Wait until the web container has created the tables.
        until php artisan migrate:status --no-interaction >/dev/null 2>&1; do sleep 2; done
        as_www php artisan queue:work --sleep=3 --tries=1 --timeout=3600
        ;;
    scheduler)
        prepare
        until php artisan migrate:status --no-interaction >/dev/null 2>&1; do sleep 2; done
        as_www php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
