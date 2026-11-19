FROM node:22-alpine@sha256:1314fb30ec37592d5845e5a160d522c0e680972aa534549940b8d73bde88481a AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-alpine@sha256:7b057eed5b9a1a67e3911ae83015963fc181a0ad886679f8f1b25dabad8873c4 AS php-base
RUN install-php-extensions pdo_pgsql pdo_mysql intl mbstring gd zip opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
WORKDIR /app

FROM php-base AS dependencies
COPY --from=composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71 /usr/bin/composer /usr/bin/composer
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && composer check-platform-reqs --no-dev

FROM php-base AS production
ENV APP_ENV=production APP_DEBUG=false PORT=8080 \
    LARAVEL_STORAGE_PATH=/tmp/meuportfolio-storage \
    APP_CONFIG_CACHE=/tmp/meuportfolio-bootstrap/config.php \
    APP_SERVICES_CACHE=/tmp/meuportfolio-bootstrap/services.php \
    APP_PACKAGES_CACHE=/tmp/meuportfolio-bootstrap/packages.php \
    APP_ROUTES_CACHE=/tmp/meuportfolio-bootstrap/routes.php \
    APP_EVENTS_CACHE=/tmp/meuportfolio-bootstrap/events.php \
    XDG_CONFIG_HOME=/tmp/caddy-config XDG_DATA_HOME=/tmp/caddy-data
COPY --from=dependencies --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /build/public/build /app/public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN chmod +x /app/docker/entrypoint.sh
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 CMD ["php", "/app/docker/healthcheck.php"]
ENTRYPOINT ["/bin/sh", "/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
