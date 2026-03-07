FROM php:8.3-fpm

# ─────────────────────────────────────────────
# 1. System dependencies required by Moodle 5.1
# ─────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libpq-dev libxml2-dev \
    libzip-dev libicu-dev libsodium-dev \
    zip unzip git curl tzdata rsync cron \
    && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────
# 2. PHP extensions
# ─────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install \
        gd intl pgsql pdo_pgsql zip opcache soap exif sodium pcntl

# Redis extension for session/cache
RUN pecl install redis && docker-php-ext-enable redis

# ─────────────────────────────────────────────
# 3. PHP tuning
# ─────────────────────────────────────────────
RUN { \
    echo "memory_limit = 512M"; \
    echo "max_execution_time = 300"; \
    echo "max_input_vars = 5000"; \
    echo "upload_max_filesize = 500M"; \
    echo "post_max_size = 500M"; \
    echo "opcache.enable = 1"; \
    echo "opcache.memory_consumption = 512"; \
    echo "opcache.max_accelerated_files = 20000"; \
    echo "opcache.revalidate_freq = 60"; \
    echo "opcache.interned_strings_buffer = 16"; \
} > /usr/local/etc/php/conf.d/moodle.ini

# ─────────────────────────────────────────────
# 4. PHP-FPM pool tuning
# ─────────────────────────────────────────────
RUN { \
    echo "[www]"; \
    echo "pm = dynamic"; \
    echo "pm.max_children = 150"; \
    echo "pm.start_servers = 20"; \
    echo "pm.min_spare_servers = 10"; \
    echo "pm.max_spare_servers = 40"; \
    echo "pm.max_requests = 500"; \
} > /usr/local/etc/php-fpm.d/zz-moodle.conf

# ─────────────────────────────────────────────
# 5. Clone Moodle v5.1.3 — Moodle 5.1+ uses a
#    public/ subdirectory for web-accessible files.
#    We install to /var/www/moodle and point nginx
#    to /var/www/moodle/public per official docs.
# ─────────────────────────────────────────────
RUN git clone \
    --depth 1 \
    --branch v5.1.3 \
    https://github.com/moodle/moodle.git \
    /var/www/moodle \
    && chown -R www-data:www-data /var/www/moodle

# ─────────────────────────────────────────────
# 6. Inject env-driven config.php
# ─────────────────────────────────────────────
COPY config.php /var/www/moodle/config.php
RUN chown www-data:www-data /var/www/moodle/config.php

# ─────────────────────────────────────────────
# 7. Cron entrypoint
# ─────────────────────────────────────────────
COPY cron/moodle-cron.sh /usr/local/bin/moodle-cron.sh
RUN chmod +x /usr/local/bin/moodle-cron.sh

# Moodle dataroot — not web-accessible
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata

WORKDIR /var/www/moodle

EXPOSE 9000
