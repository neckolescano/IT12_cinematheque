# Cinematheque Centre Davao — production image (PHP 8.2 + Apache), for Render / Koyeb / any Docker host.
#   docker build -t ccd .
#   docker run -p 8080:8080 --env-file .env.production ccd
FROM php:8.2-apache

# PHP extensions the app needs: pdo_mysql (database), bcmath + intl (Laravel helpers). No GD, no zip needed.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev \
    && docker-php-ext-install pdo_mysql bcmath intl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Apache serves Laravel's public/ folder and follows .htaccess rewrites.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Dependencies first (cached between builds), then the application.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# The host sets PORT (Render: 10000, Koyeb: 8000); 8080 when run locally.
ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
