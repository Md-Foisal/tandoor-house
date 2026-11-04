#!/bin/sh
# Every start is a fresh demo: new secrets, new database, today's dates.
set -e

random() { php -r 'echo base64_encode(random_bytes(32));'; }

export APP_URL="${RENDER_EXTERNAL_URL:-${APP_URL:-http://localhost:$PORT}}"
export APP_KEY="base64:$(random)"
export REVERB_APP_SECRET="$(random)"

sed -i "s/__PORT__/$PORT/" /etc/nginx/nginx.conf

rm -f database/database.sqlite*
touch database/database.sqlite
php artisan storage:link --force --no-interaction >/dev/null
php artisan migrate --force --no-interaction >/dev/null
php -d memory_limit=-1 artisan db:seed --force --no-interaction >/dev/null
php artisan optimize --no-interaction >/dev/null
chown -R www-data:www-data storage bootstrap/cache database

# Small loops rather than a process manager: every megabyte counts here.
keep() {
    while true; do
        "$@" || true
        sleep 2
    done
}

keep php artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction &
keep php artisan queue:work --sleep=3 --tries=3 --max-time=1800 --memory=96 --no-interaction &
(while true; do
    sleep 60
    php artisan schedule:run --no-interaction >/dev/null || true
done) &
php-fpm &

exec nginx -g 'daemon off;'
