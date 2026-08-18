# Syntax: dockerfile
# ---- Stage 1: build the React frontend ----
FROM node:22-alpine AS frontend
WORKDIR /src

COPY frontend/package*.json ./frontend/
RUN cd frontend && npm install

COPY frontend ./frontend
# The frontend build writes its output into ../backend/public/build (see vite.config.ts).
COPY backend ./backend
RUN cd frontend && npm run build

# ---- Stage 2: PHP + Apache serving the Symfony app ----
FROM php:8.3-apache AS runtime

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --from=frontend /src/backend /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && cd /var/www/html \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && rm -rf var/cache/*

COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
