# Development image. The application source is mounted by Compose.
FROM php:8.5-cli-alpine

ARG PHP_BUILD_JOBS=2

RUN apk add --no-cache icu-libs icu-data-full libzip oniguruma sqlite-libs unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install -j${PHP_BUILD_JOBS} bcmath ctype intl mbstring pcntl pdo_mysql pdo_sqlite zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker-php.ini /usr/local/etc/php/conf.d/99-development.ini

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_NO_INTERACTION=1
WORKDIR /var/www/html

USER 1000:1000
EXPOSE 8000
STOPSIGNAL SIGTERM

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
