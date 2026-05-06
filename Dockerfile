# Stage 1: Build PHP dependencies
FROM composer:latest AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Stage 2: Build JS/CSS assets
FROM node:18-alpine AS node
WORKDIR /var/www/html
# Copy all files first to ensure Mix has everything it needs (configs, assets, domain logic)
COPY . .
RUN npm install
RUN npm run prod

# Stage 3: Final production image
FROM php:8.2-fpm-alpine

# Install ONLY necessary runtime dependencies
RUN apk add --no-cache \
    nginx \
    gettext \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    libldap \
    icu-libs \
    oniguruma \
    libxml2 \
    bash

# Install PHP extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions
RUN install-php-extensions \
    gd \
    mysqli \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache \
    ldap \
    zip \
    intl

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo -e "opcache.memory_consumption=256\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=20000\nopcache.revalidate_freq=0\nopcache.validate_timestamps=0\nopcache.fast_shutdown=1" > /usr/local/etc/php/conf.d/opcache-optimized.ini

# Copy Nginx config template
COPY nginx.conf /etc/nginx/nginx.conf.template

# Set working directory
WORKDIR /var/www/html

# Copy project files (respecting .dockerignore)
COPY . .

# Copy compiled assets from the node builder stage
COPY --from=node /var/www/html/public/dist ./public/dist

# Copy vendor from the vendor builder stage
COPY --from=vendor /var/www/html/vendor ./vendor

# Set permissions for Leantime folders
RUN sed -i 's/pm.max_children = 5/pm.max_children = 20/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.start_servers = 2/pm.start_servers = 5/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 5/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 10/g' /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && mkdir -p public/userfiles \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 public \
    && chmod -R 775 storage public/userfiles

# Expose port
EXPOSE 80

# Command to replace ${PORT} in nginx config and start services
CMD /bin/bash -c "envsubst '\${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/http.d/default.conf && php-fpm -D && nginx -g 'daemon off;'"
