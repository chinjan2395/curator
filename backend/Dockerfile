FROM php:8.4-fpm

USER root

# Install system dependencies and PHP extensions needed by Laravel (added libpq-dev for Postgres).
RUN apt-get update && \
    apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip git unzip ffmpeg iputils-ping ghostscript libmagickwand-dev libpq-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd pdo pdo_mysql pdo_pgsql pgsql bcmath intl exif

# Install PHP Imagick extension.
RUN pecl install imagick && docker-php-ext-enable imagick

# Install Composer.
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Laravel backend source.
COPY --chown=33:33 . /var/www/html

# --- NEW: Install Composer Dependencies ---
# We run this after copying the source code so composer.json and composer.lock are present.
RUN composer install --no-interaction --optimize-autoloader
# ------------------------------------------

# Set runtime permissions for Laravel writable directories.
RUN chmod -R 755 /var/www/html && \
    if [ -d storage ]; then chmod -R 777 storage; fi && \
    if [ -d bootstrap/cache ]; then chmod -R 775 bootstrap/cache; fi && \
    chown -R www-data:www-data /var/www/html

EXPOSE 8000

USER www-data

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
