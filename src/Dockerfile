FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm install
COPY resources ./resources
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

FROM php:8.4-fpm-alpine AS app
ARG GITLEAKS_VERSION=8.21.2
# PHP 8.4: Laravel 13 / Symfony 8 membutuhkannya (PRD menyebut 8.3+).
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_mysql intl zip gd bcmath exif opcache pcntl redis \
 && apk add --no-cache git docker-cli docker-cli-compose curl \
 && A=$(uname -m | sed 's/x86_64/x64/;s/aarch64/arm64/') \
 && curl -sSL https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_${A}.tar.gz \
      | tar -xz -C /usr/local/bin gitleaks
WORKDIR /var/www/html
COPY docker/php/*.ini /usr/local/etc/php/conf.d/
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN php artisan package:discover && php artisan filament:assets \
 && php artisan storage:link && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

# Nginx membawa aset publik dari image aplikasi (tidak lagi bind-mount ./public yang bisa usang).
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
