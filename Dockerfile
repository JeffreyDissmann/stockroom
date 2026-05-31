# syntax=docker/dockerfile:1.7

# -----------------------------------------------------------------------------
# Stage 1 — build the frontend (production Vite bundle)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy only manifest first so the npm layer caches across source changes.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Bring in the source needed by Vite + Wayfinder codegen + Tailwind content scan.
COPY resources ./resources
COPY public ./public
COPY vite.config.ts tailwind.config.js tsconfig.json ./

# Wayfinder is regenerated from PHP routes at build time. We don't have PHP here,
# so we rely on the committed generated tree under resources/js/{actions,routes,wayfinder}
# (the same tree CI enforces via `npm run wayfinder:check`).
RUN npm run build


# -----------------------------------------------------------------------------
# Stage 2 — install PHP dependencies (no dev, optimized autoloader)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Manifest first → cached layer that survives source edits.
COPY composer.json composer.lock ./

# Pull deps without running scripts (artisan isn't there yet) and skip platform
# checks because the build container's PHP may differ from the runtime.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs

# Now copy the real source and finish autoload + scripts.
COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative


# -----------------------------------------------------------------------------
# Stage 3 — runtime (FrankenPHP, PHP 8.5)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5

# install-php-extensions ships in the image and handles compile flags + deps.
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    gd \
    exif \
    bcmath \
    pcntl \
    opcache \
    redis

WORKDIR /app

# Bring in the app, then the optimized vendor/, then the built frontend assets.
# Each layer is its own COPY so partial rebuilds stay cheap.
COPY --from=vendor /app /app
COPY --from=frontend /app/public/build /app/public/build

# Production PHP / opcache tuning. The defaults FrankenPHP ships with are dev-y.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-stockroom.ini

# Caddy config: serve from /app/public on the port FrankenPHP_PORT exposes.
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile

# Entrypoint orchestrates first-boot housekeeping (key, migrate, caches, admin
# seed) before exec'ing the requested role (web / queue / scheduler).
COPY docker/entrypoint.sh /usr/local/bin/stockroom-entrypoint
RUN chmod +x /usr/local/bin/stockroom-entrypoint

# Storage and bootstrap caches need to be writable. Pre-create the layout so
# the first request doesn't trip over a missing dir.
RUN set -eux; \
    mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

# Build-time provenance: the release workflow passes the CalVer tag + the
# commit SHA so the running container can identify itself (login page
# chip, future "About" surfaces). Defaults are empty so local builds
# without these args silently disable the version chip rather than
# rendering "(unknown)".
ARG APP_VERSION=""
ARG APP_COMMIT=""

# FrankenPHP listens on 8080 by default in this image; reverse-proxy in front
# for TLS. Override with SERVER_NAME if you terminate TLS here.
ENV SERVER_NAME=:8080 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    STOCKROOM_ROLE=web \
    APP_VERSION=${APP_VERSION} \
    APP_COMMIT=${APP_COMMIT}

EXPOSE 8080

ENTRYPOINT ["stockroom-entrypoint"]
