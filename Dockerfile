FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-dev oniguruma-dev libzip-dev mysql-client bash nginx supervisor \
    && docker-php-ext-install bcmath intl mbstring opcache pdo_mysql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json ./
COPY composer.lock* ./
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts; \
    else \
        composer update --no-dev --prefer-dist --no-interaction --no-progress --no-scripts; \
    fi
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
