FROM php:8.3-fpm

# 1. Install system dependencies required by Moodle 5.1.x
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libpq-dev libxml2-dev \
    libzip-dev libicu-dev libsodium-dev \
    zip unzip git curl tzdata rsync \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd intl pgsql pdo_pgsql zip opcache soap exif sodium

# 3. Install Redis extension for Session/Cache handling
RUN pecl install redis && docker-php-ext-enable redis

# 4. PHP Tuning for 10GB RAM / 6 vCPUs (Burst Setup)
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.memory_consumption = 1024" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.max_accelerated_files = 10000" >> /usr/local/etc/php/conf.d/moodle.ini

# 5. PHP-FPM High Concurrency Tuning
RUN echo "[www]\npm.max_children = 200\npm.start_servers = 20\npm.min_spare_servers = 20\npm.max_spare_servers = 50\npm.max_requests = 1000" >> /usr/local/etc/php-fpm.d/zz-moodle.conf

# 6. Git clone specific tag (v5.1.3) from official Moodle repository
RUN git clone --depth 1 --branch v5.1.3 git://git.moodle.org/moodle.git /var/www/html \
    && chown -R www-data:www-data /var/www/html

# 7. Inject our dynamic config
COPY config.php /var/www/html/config.php
RUN chown www-data:www-data /var/www/html/config.php

WORKDIR /var/www/html