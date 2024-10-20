FROM php:8.3-cli-alpine

RUN apk add unzip libpq-dev git icu-dev \
    && docker-php-ext-install bcmath pdo pdo_pgsql intl \
    && docker-php-ext-enable bcmath pdo pdo_pgsql intl

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

ENV COMPOSER_ALLOW_SUPERUSER 1
