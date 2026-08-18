# syntax=docker/dockerfile:1

#
# Stage 1 — PHP dependencies (no dev packages, no scripts until source is present)
#
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-req=ext-intl \
    --ignore-platform-req=ext-gd

#
# Stage 2 — front-end asset build
#
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

#
# Stage 3 — runtime image
#
FROM php:8.5-fpm-alpine AS runtime

ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN apk add --no-cache \
        nginx \
        supervisor \
        sqlite \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        sqlite-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Non-root application user (fixed uid/gid so bind-mounted volumes stay writable)
RUN addgroup -g "${WWWGROUP}" -S app \
    && adduser -u "${WWWUSER}" -S app -G app

COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --from=assets --chown=app:app /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-solar-production.ini
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-solar.conf
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisor.d/supervisord.ini
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/production/healthcheck-watchdog.sh /usr/local/bin/healthcheck-watchdog.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/healthcheck-watchdog.sh

# nginx/php-fpm runtime dirs — owned by the unprivileged app user so the
# whole process tree (supervisord, nginx, php-fpm) can run as non-root
RUN mkdir -p /run/nginx /run/php-fpm /var/lib/nginx/tmp /var/log/supervisor \
    && chown -R app:app /run/nginx /run/php-fpm /var/lib/nginx /var/log/nginx /var/log/supervisor

USER app

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor.d/supervisord.ini", "-n"]
