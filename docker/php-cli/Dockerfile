FROM php:8.4-cli-alpine

RUN apk add unzip libpq-dev git icu-dev autoconf build-base linux-headers \
    && docker-php-ext-install bcmath pdo pdo_pgsql intl \
    && docker-php-ext-enable bcmath pdo pdo_pgsql intl

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host = host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port = 9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN git config --global --add safe.directory /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

ENV COMPOSER_ALLOW_SUPERUSER=1
