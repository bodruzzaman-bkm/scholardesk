# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — build the front-end assets.
#
# Node 22 rather than the 20.10 on the dev machine: Vite 8 requires
# ^20.19 || >=22.12, which is why `npm run build` fails locally and the CSS has
# been generated with the Tailwind CLI as a stopgap. Building here produces a
# real Vite bundle with a correct manifest.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

# Copy the manifests first so this layer is cached unless dependencies change.
COPY package.json package-lock.json ./
RUN npm ci

# Tailwind scans the Blade templates, so they must be present at build time.
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2 — PHP dependencies, without dev packages.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Composer pulls 78 packages as zipballs from api.github.com, which returns
# intermittent 504s. Two things make that survivable:
#
#   * No --prefer-dist. Passing it explicitly makes dist downloads mandatory
#     ("Source fallback is disabled. Not trying alternative sources."), so one
#     bad gateway response fails the whole build. Without it Composer still
#     prefers dist but falls back to a git clone for anything it cannot fetch.
#   * Retries, because a 504 is transient by nature and a second attempt
#     usually succeeds where the first did not.
RUN composer config --global process-timeout 600 \
    && for attempt in 1 2 3; do \
         echo "composer install (attempt $attempt)"; \
         composer install \
           --no-dev \
           --no-scripts \
           --no-interaction \
           --optimize-autoloader \
         && break; \
         if [ "$attempt" = 3 ]; then echo "composer install failed after 3 attempts"; exit 1; fi; \
         echo "retrying in 10s..."; sleep 10; \
       done


# ---------------------------------------------------------------------------
# Stage 3 — runtime.
# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine

# The official PHP image already bundles mbstring, dom, simplexml, iconv,
# openssl, curl, fileinfo and pdo_sqlite, which covers everything composer.lock
# requires. Added here are the ones it does NOT ship:
#   zip     — composer package extraction
#   intl    — locale-aware formatting used by the bilingual UI
#   bcmath  — arbitrary-precision arithmetic Laravel expects
#   opcache — compiled-script cache; a large win for a PHP app under load
# gd is deliberately absent: smalot/pdfparser only needs it for image
# extraction, and this app reads text.
RUN apk add --no-cache \
      sqlite-libs \
      libzip \
      icu-libs \
      oniguruma \
    && apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      sqlite-dev \
      libzip-dev \
      icu-dev \
      oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
      zip \
      intl \
      bcmath \
      opcache \
    && apk del .build-deps

# Uploads: the app caps a PDF at 10 MB, so the request ceiling is set above it
# with room for multipart overhead and a multi-file batch.
RUN { \
      echo 'upload_max_filesize=20M'; \
      echo 'post_max_size=128M'; \
      echo 'memory_limit=512M'; \
      echo 'max_execution_time=120'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/scholardesk.ini

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Builds the package manifest from the packages actually installed here. The
# local one is excluded by .dockerignore because it lists dev dependencies that
# --no-dev never installed.
#
# mkdir first: excluding bootstrap/cache/*.php can leave the directory absent
# from the build context, and Laravel needs it to exist and be writable.
RUN mkdir -p bootstrap/cache storage/framework/cache/data \
                              storage/framework/sessions \
                              storage/framework/views \
                              storage/logs \
    && php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
