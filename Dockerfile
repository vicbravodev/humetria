# syntax=docker/dockerfile:1.7
#
# Imagen de producción de Humetría (la de desarrollo es la de Sail, compose.yaml).
# Una sola imagen para web, horizon y scheduler: cambia sólo el `command` (compose.production.yaml).
# Base serversideup/php: nginx + php-fpm sin root, escucha en 8080, AUTORUN (caches + migrate) al arrancar.

ARG PHP_IMAGE=serversideup/php:8.5-fpm-nginx-debian-v4.5.3
ARG NODE_IMAGE=node:24-trixie-slim

FROM ${NODE_IMAGE} AS node

# --- base: extensiones que pide composer.json además de las de la imagen ----
FROM ${PHP_IMAGE} AS base
USER root
RUN install-php-extensions gd exif
USER www-data
WORKDIR /var/www/html

# --- vendor: dependencias PHP de producción ---------------------------------
FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# --- app sin assets: código + vendor + autoload y package:discover ----------
FROM base AS source
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# --- assets: bundle de Vite (Wayfinder corre `php artisan wayfinder:generate`) ---
FROM source AS assets
USER root
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
USER www-data
RUN npm ci --no-audit --no-fund && npm run build

# --- app: imagen final ------------------------------------------------------
FROM source AS app

# Buffers de FastCGI: con los 8k por defecto nginx corta las respuestas de Inertia (sesión, CSP con nonce, Link)
# con un 502 "too big header".
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_MEMORY_LIMIT=512M \
    NGINX_FASTCGI_BUFFER_SIZE=32k \
    NGINX_FASTCGI_BUFFERS="16 16k"

COPY --from=assets --chown=www-data:www-data /var/www/html/public/build ./public/build

ARG GIT_SHA=unknown
LABEL org.opencontainers.image.source="https://github.com/vicbravodev/humetria" \
      org.opencontainers.image.revision="${GIT_SHA}"
