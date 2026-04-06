FROM php:8.3-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev \
  && rm -rf /var/lib/apt/lists/* \
  && a2enmod rewrite \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli pdo_pgsql pgsql

# Allow .htaccess overrides (needed for the app routes)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy the PHP app (Render web root should be the project root; we map this into Apache docroot)
WORKDIR /var/www/html
COPY srms/script/ ./

# Ensure writable dirs (uploads/logos etc.)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
