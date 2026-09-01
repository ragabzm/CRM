# syntax=docker/dockerfile:1

# PHP 8.5 as the story specifies. The local toolchain is currently 8.4 (see
# backend/README.md) — the image is the place where 8.5 is real, and composer.json
# allows ^8.4 so both resolve the same dependency set.
FROM php:8.5-fpm-alpine AS base

# Two things to know about this step:
#
#  1. Extensions are installed one at a time. docker-php-ext-install shares a
#     build directory across the extensions named in one invocation, so passing
#     several together with -j races and fails on "cp: can't stat 'modules/*'".
#  2. OPcache is NOT in the list: PHP 8.5's official image ships it compiled in,
#     and asking docker-php-ext-install to build it produces no module and
#     fails. It is enabled via conf.d in the runtime stage below.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev linux-headers; \
    apk add --no-cache libpq icu-libs; \
    for ext in pdo_pgsql pgsql intl pcntl bcmath; do \
        docker-php-ext-install -j"$(nproc)" "$ext"; \
    done; \
    apk del --no-network .build-deps; \
    php -m

WORKDIR /app

# --- dependency layer -------------------------------------------------------
# Copied separately so that editing application code does not invalidate the
# (slow) composer install layer.
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --no-autoloader

# --- runtime ----------------------------------------------------------------
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=json \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

# Production opcache settings; the defaults are tuned for development.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini"

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]

# Overridden per service in docker-compose.yml: web | worker | scheduler.
CMD ["web"]
