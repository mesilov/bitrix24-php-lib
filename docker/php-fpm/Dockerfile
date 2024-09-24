FROM php:8.2-fpm-alpine


RUN set -ex \
 && apk --no-cache add \
 postgresql-dev \
 && docker-php-ext-install pdo pdo_pgsql

WORKDIR /bitrix24-php-lib

COPY ./ ./