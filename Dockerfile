FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    libxslt1-dev \
    libzip-dev \
    libldap-dev \
    libpng-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# AGGRESSIVE FIX for "More than one MPM loaded"
# We remove the physical files for event and worker MPMs to prevent Apache from loading them
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork || true

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    gd \
    mysqli \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache \
    ldap \
    zip

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Add OPcache configuration
RUN echo "opcache.memory_consumption=256\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=20000\nopcache.revalidate_freq=0\nopcache.validate_timestamps=0\nopcache.fast_shutdown=1" > /usr/local/etc/php/conf.d/opcache-optimized.ini

# Expose port 80
EXPOSE 80
