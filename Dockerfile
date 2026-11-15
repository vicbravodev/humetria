# syntax=docker/dockerfile:1.7
#
# Imagen de producción de Humetría (la de desarrollo es la de Sail, compose.yaml).
# Una sola imagen para web, horizon y scheduler: cambia sólo el `command` (compose.production.yaml).
# Hay una segunda, `--target ssr`: el servicio de Node que renderiza la landing (sin PHP, sin .env, sin vendor).
# Las dos salen del mismo commit y del mismo `docker build` de la etapa `assets` (GIT_SHA las sella igual).
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
# Cliente y bundle de SSR salen de la misma ejecución, con el commit en VITE_BUILD_REVISION (meta build-revision).
FROM source AS assets
USER root
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
USER www-data
ARG GIT_SHA=unknown
ENV VITE_BUILD_REVISION=$GIT_SHA
RUN npm ci --no-audit --no-fund && npm run build:ssr

# --- ssr: el servidor de Node de Inertia con su bundle (no es la imagen final: `docker build .` sigue dando `app`) ---
# El bundle trae sus dependencias (vite `ssr.noExternal`): no hace falta node_modules. Es ESM y la imagen no trae
# package.json, de ahí el `{"type":"module"}`. El vigilante reinicia el contenedor si Node se cuelga (scripts/ssr/supervisor.mjs).
FROM ${NODE_IMAGE} AS ssr
WORKDIR /srv/ssr
COPY --from=assets --chown=node:node /var/www/html/bootstrap/ssr ./bootstrap/ssr
COPY --chown=node:node scripts/ssr ./scripts/ssr
# La lista de lo que la landing debe decir: el healthcheck y el vigilante la leen con la misma ruta relativa que en el repo.
COPY --chown=node:node tests/Ssr/fixtures/landing-expected.json ./tests/Ssr/fixtures/landing-expected.json
RUN printf '{"type":"module"}' > package.json
USER node
EXPOSE 13714
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD ["node", "scripts/ssr/healthcheck.mjs"]
CMD ["node", "scripts/ssr/supervisor.mjs"]

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
# La revisión que imprime Blade (meta app-revision). El .env de producción no la define: uno vacío pisaría ésta.
ENV APP_REVISION=${GIT_SHA}
LABEL org.opencontainers.image.source="https://github.com/vicbravodev/humetria" \
      org.opencontainers.image.revision="${GIT_SHA}"
