# The public demo: one container holding the web server, PHP, the queue worker,
# the scheduler and Reverb, with SQLite. A real restaurant needs MySQL and
# separate processes; see docs/decisions.md.

FROM php:8.4-cli-bookworm AS build
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update -qq && apt-get install -y -qq --no-install-recommends curl unzip git nodejs npm >/dev/null \
    && npm install -g n >/dev/null && n 22 >/dev/null \
    && install-php-extensions bcmath exif gd intl pcntl sockets zip
WORKDIR /app
COPY . .
# The browser connects to the page's own host through nginx, so no host is set.
# APP_ENV=local stops route generation baking a root URL into the browser code.
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_ENV=local \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    BROADCAST_CONNECTION=log \
    VITE_APP_NAME="Tandoor House" \
    VITE_REVERB_APP_KEY=tandoor-demo \
    VITE_REVERB_SCHEME=https \
    VITE_REVERB_PORT=443
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
RUN npm ci --no-audit --no-fund && npm run build && rm -rf node_modules public/hot

FROM php:8.4-fpm-alpine AS runtime
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apk add --no-cache nginx \
    && install-php-extensions bcmath exif gd intl opcache pcntl sockets zip \
    && rm /usr/local/bin/install-php-extensions
WORKDIR /app
COPY --from=build /app /app
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/demo.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-demo.conf
COPY docker/start.sh /usr/local/bin/start-demo
RUN chmod +x /usr/local/bin/start-demo \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database
ENV APP_ENV=production \
    APP_NAME="Tandoor House" \
    APP_LOCALE=en_GB \
    APP_FALLBACK_LOCALE=en_GB \
    APP_FAKER_LOCALE=en_GB \
    APP_DEBUG=false \
    DEMO_MODE=true \
    PORT=10000 \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    TRUSTED_PROXIES=* \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    DB_JOURNAL_MODE=wal \
    DB_BUSY_TIMEOUT=5000 \
    DB_SYNCHRONOUS=normal \
    QUEUE_CONNECTION=database \
    SESSION_DRIVER=database \
    CACHE_STORE=file \
    BROADCAST_CONNECTION=reverb \
    REVERB_APP_ID=tandoor-demo \
    REVERB_APP_KEY=tandoor-demo \
    REVERB_HOST=127.0.0.1 \
    REVERB_PORT=8080 \
    REVERB_SCHEME=http \
    REVERB_SERVER_HOST=127.0.0.1 \
    REVERB_SERVER_PORT=8080
EXPOSE 10000
CMD ["start-demo"]
