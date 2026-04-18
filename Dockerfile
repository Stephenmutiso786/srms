FROM php:8.3-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev \
  && rm -rf /var/lib/apt/lists/* \
  && a2enmod rewrite \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli pdo_pgsql pgsql

# PHP runtime config (hide warnings/notices in production logs while keeping logs enabled)
COPY php.ini /usr/local/etc/php/conf.d/99-elimu.ini

# Allow .htaccess overrides (needed for the app routes)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy the PHP app (for DigitalOcean App Platform and any Docker host)
WORKDIR /var/www/html
COPY srms/script/ ./
COPY srms/database/ ./database/

# Ensure writable dirs (uploads/logos etc.)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
