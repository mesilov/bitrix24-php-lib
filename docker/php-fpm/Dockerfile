FROM php:8.2-fpm-alpine

RUN set -ex \
 && apk --no-cache add \
 postgresql-dev \
 oniguruma-dev \
 libzip-dev \
 curl-dev \
 && docker-php-ext-install pdo pdo_pgsql mbstring zip curl bcmath

WORKDIR /bitrix24-php-lib

COPY ./ ./