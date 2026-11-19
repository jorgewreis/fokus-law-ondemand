# Local synthetic runtime baseline. Production deployment remains gated by ADR-0001.
FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.5-apache-bookworm AS app
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install bcmath mbstring pdo_mysql xml zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/ ./
COPY --from=frontend --chown=www-data:www-data /app/public/ ./public/
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
