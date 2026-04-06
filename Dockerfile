FROM php:8.3-apache

RUN a2enmod rewrite \
  && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli

# Allow .htaccess overrides (needed for the app routes)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy the PHP app (Render web root should be the project root; we map this into Apache docroot)
WORKDIR /var/www/html
COPY srms/script/ ./

# Ensure writable dirs (uploads/logos etc.)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

