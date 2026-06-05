FROM php:8.4-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    shadow \
    supervisor \
    unzip \
    zip \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    nodejs \
    npm

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        ctype \
        dom \
        exif \
        fileinfo \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        tokenizer \
        xml \
        zip

# Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Create app user
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser

WORKDIR /var/www

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# -------------------------------------------------------
FROM base AS development

USER root

COPY --chown=appuser:appuser . .

# Install PHP deps
RUN composer install --no-scripts --no-interaction

# Install Node deps and build assets
RUN npm ci && npm run build

RUN chown -R appuser:appuser storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

USER appuser

EXPOSE 9000

CMD ["php-fpm"]

# -------------------------------------------------------
FROM base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

USER root

COPY --chown=appuser:appuser . .

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

RUN npm ci && npm run build && rm -rf node_modules

RUN chown -R appuser:appuser storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
