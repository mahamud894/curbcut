# syntax=docker/dockerfile:1
#
# Curbcut — one image for the web app, the queue worker and the scheduler.
# Build:  docker build -t curbcut .
# Run:    docker compose up -d   (see docker-compose.yml and docs/docker.md)

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# ---- 1. Front-end assets and the scanner's Node packages --------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
COPY vite.config.js tsconfig.json components.json ./
RUN npm run build \
 && mkdir -p /scanner/node_modules \
 && cp -r node_modules/playwright-core node_modules/axe-core /scanner/node_modules/

# ---- 2. PHP dependencies ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- 3. Runtime -------------------------------------------------------------
FROM php:${PHP_VERSION}-apache-bookworm

LABEL org.opencontainers.image.source="https://github.com/mahamud894/curbcut" \
      org.opencontainers.image.description="Curbcut: self-hosted web accessibility monitoring (WCAG 2.2 AA)" \
      org.opencontainers.image.licenses="AGPL-3.0-only"

# Chromium does the scanning and the PDF reports; the fonts keep reports readable.
RUN apt-get update \
 && apt-get install -y --no-install-recommends chromium fonts-liberation fonts-noto-core fonts-noto-color-emoji sqlite3 libpq-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install -j"$(nproc)" opcache pcntl pdo_mysql pdo_pgsql \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && a2enmod rewrite headers

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-curbcut.ini"
COPY docker/entrypoint.sh /usr/local/bin/curbcut-entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chown=www-data:www-data --from=assets /scanner/node_modules ./node_modules

RUN chmod +x /usr/local/bin/curbcut-entrypoint \
 && mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data /data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    CURBCUT_NODE_BINARY=/usr/local/bin/node \
    CURBCUT_CHROME_PATH=/usr/bin/chromium \
    HOME=/tmp

VOLUME /data
EXPOSE 80
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=6 CMD curl -fsS http://localhost/up || exit 1

ENTRYPOINT ["curbcut-entrypoint"]
CMD ["web"]
