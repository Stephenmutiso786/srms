FROM php:8.3-apache

# Install critical extensions + Redis support
RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev redis-tools \
  && rm -rf /var/lib/apt/lists/* \
  && a2enmod rewrite deflate headers expires \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli pdo_pgsql pgsql \
  && pecl install redis && docker-php-ext-enable redis

# PHP runtime config for performance
COPY php.ini /usr/local/etc/php/conf.d/99-elimu.ini

# Apache performance config
RUN echo "StartServers 2\nMinSpareServers 2\nMaxSpareServers 4\nMaxRequestWorkers 20\nMaxConnectionsPerChild 100" > /etc/apache2/mods-available/mpm_prefork.conf

# Allow .htaccess overrides (needed for the app routes)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Add response caching headers
RUN echo '\nSetEnvIf Request_URI "^/images/" CACHE_PUBLIC\nSetEnvIf Request_URI "^/css/" CACHE_PUBLIC\nSetEnvIf Request_URI "^/js/" CACHE_PUBLIC\nSetEnvIf Request_URI "^/cdn" CACHE_PUBLIC\nHeader set Cache-Control "public, max-age=86400" env=CACHE_PUBLIC' >> /etc/apache2/apache2.conf

# Create cache directory
RUN mkdir -p /var/www/html/cache && chmod 77
7 /var/www/html/cache

# Copy the PHP app
WORKDIR /var/www/html
COPY srms/script/ ./
COPY srms/database/ ./database/

# Ensure writable dirs
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
