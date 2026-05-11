FROM php:8.4-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
    aria2 \
    bash \
    curl \
    nginx \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    ffmpeg \
    py3-pip \
    yt-dlp \
    unzip \
    zip \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pcntl \
    pdo_pgsql \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && python3 -m pip install --break-system-packages --no-cache-dir bgutil-ytdlp-pot-provider \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM php-base AS frontend

RUN apk add --no-cache nodejs npm

COPY package*.json ./
RUN npm ci

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN php artisan wayfinder:generate --with-form --no-interaction \
    && npm run build

FROM php-base AS app

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend /var/www/html/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/downloora-entrypoint
COPY docker/php/nginx.conf /etc/nginx/http.d/default.conf

RUN chmod +x /usr/local/bin/downloora-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["downloora-entrypoint"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
