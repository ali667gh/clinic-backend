# syntax=docker/dockerfile:1

FROM laravel-prod-base:1.0

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
      --no-dev \
      --prefer-dist \
      --no-interaction \
      --no-progress \
      --no-scripts \
      --no-autoloader

COPY --chown=www-data:www-data . .

RUN mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache \
 && composer dump-autoload --no-dev --optimize \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080