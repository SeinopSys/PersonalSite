# PersonalSite: one build, two runtime images (targets: fpm, web). Context is the repository root.
#   docker buildx bake -f docker/docker-bake.hcl
# Environment is runtime configuration only (env vars / mounted Secret); nothing secret is baked in.
# Uploaded files live on a volume mounted at public/storage (storage/app/public is a symlink to it) and are NOT in the image.
ARG PHP_IMAGE=php:8.5-fpm-trixie
ARG NODE_IMAGE=node:24-trixie-slim

FROM --platform=$BUILDPLATFORM ${PHP_IMAGE} AS php-base-build
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl opcache pcntl pdo_pgsql redis zip \
 && rm -rf /var/lib/apt/lists/* /tmp/*

FROM ${PHP_IMAGE} AS php-base
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl opcache pcntl pdo_pgsql redis zip \
 && rm -rf /var/lib/apt/lists/* /tmp/*

FROM --platform=$BUILDPLATFORM ${NODE_IMAGE} AS node-bin
FROM --platform=$BUILDPLATFORM composer:2 AS composer-bin

# ---------------------------------------------------------------------------------------------------------
FROM php-base-build AS build
ENV COMPOSER_ALLOW_SUPERUSER=1 CI=true
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node
COPY --from=node-bin /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && npm install -g pnpm@11 \
 && apt-get update && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts
# dummy env only so artisan can boot while building; no config:cache/optimize, the real env is injected at runtime
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= APP_ENV=production \
    sh -c 'php artisan package:discover --ansi && pnpm run build'

# runtime tree: everything the app needs at runtime, minus node tooling and tests
RUN mkdir /runtime && cp -a /app/. /runtime/ \
 && rm -rf /runtime/node_modules /runtime/tests /runtime/coverage /runtime/.git /runtime/docs /runtime/.env /runtime/storage/*.key /runtime/storage/rootCA.* /runtime/public/storage

# ---------------------------------------------------------------------------------------------------------
FROM php-base AS fpm
ARG GIT_COMMIT_HASH=unknown
ARG GIT_COMMIT_DATE=unknown
RUN { echo "memory_limit = 256M"; echo "expose_php = Off"; \
      echo "upload_max_filesize = 20M"; echo "post_max_size = 24M"; \
      echo "opcache.enable=1"; echo "opcache.validate_timestamps=0"; echo "opcache.memory_consumption=96"; \
      echo "opcache.max_accelerated_files=20000"; } > /usr/local/etc/php/conf.d/99-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /runtime/ ./
# GIT_COMMIT_HASH is the FULL 40-hex sha: app/Util/Core.php reads it (and the date) from .git-deploy-commit for the footer
RUN printf '%s\n%s\n' "${GIT_COMMIT_HASH}" "${GIT_COMMIT_DATE}" > .git-deploy-commit \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/storage \
 && rm -rf storage/app/public && ln -s /var/www/html/public/storage storage/app/public \
 && ln -s /var/www/html/public/storage/_legacy/files public/files \
 && chown -R www-data:www-data storage bootstrap/cache .git-deploy-commit public/storage
USER www-data
EXPOSE 9000

# ---------------------------------------------------------------------------------------------------------
FROM nginx:1-alpine-slim AS web
# static assets + index.php only; PHP runs in the fpm container of the same pod (same absolute paths).
# public/storage is the same uploads volume as in the fpm container, mounted read-only here.
COPY --from=build /runtime/public /var/www/html/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
# legacy real files (vinyl's public/files) lives on the same uploads volume under _legacy/
RUN ln -s /var/www/html/public/storage/_legacy/files /var/www/html/public/files
EXPOSE 8080
