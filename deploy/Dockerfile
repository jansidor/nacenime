# Produkčný obraz API (php-fpm). Build z koreňa repa: docker build -f deploy/Dockerfile -t nacenime .
FROM composer:2 AS vendor
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-libs sqlite-libs \
 && apk add --no-cache --virtual .build icu-dev sqlite-dev curl-dev oniguruma-dev \
 && docker-php-ext-install intl mbstring pdo_sqlite curl \
 && apk del .build
WORKDIR /app
COPY backend/src backend/src
COPY backend/public backend/public
COPY backend/bin backend/bin
COPY design_handoff_nacenime/data/nacenime-cennik.json data/nacenime-cennik.json
COPY --from=vendor /app/backend/vendor backend/vendor
# Cenník je mimo verejného priečinka; DB leží vo volume a musí byť zapisovateľná.
ENV NACENIME_CENNIK_PATH=/app/data/nacenime-cennik.json \
    NACENIME_DB_PATH=/app/var/nacenime.sqlite
RUN mkdir -p /app/var && chown -R www-data:www-data /app/var
USER www-data
VOLUME /app/var
