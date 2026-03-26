# ─────────────────────────────────────────────
# Base image has all PHP extensions pre-compiled.
# Build it once with: docker build -f Dockerfile.base -t smamseven/moodle-php-base:8.3 .
# Push once with:     docker push smamseven/moodle-php-base:8.3
# Subsequent deploys via Coolify skip compilation (~10s instead of ~5min).
# ─────────────────────────────────────────────
FROM smamseven/moodle-php-base:8.3

# ─────────────────────────────────────────────
# 1. Clone Moodle v5.1.3 — Moodle 5.1+ uses a
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
# 2. Inject env-driven config.php
#    Stored at /etc/moodle/config.php (image layer)
#    so the entrypoint can always copy it into the
#    moodle_app volume, overriding any stale version.
# ─────────────────────────────────────────────
COPY config.php /etc/moodle/config.php
COPY config.php /var/www/moodle/config.php
RUN chown www-data:www-data /etc/moodle/config.php /var/www/moodle/config.php

# ─────────────────────────────────────────────
# 3. Cron entrypoint
# ─────────────────────────────────────────────
COPY cron/moodle-cron.sh /usr/local/bin/moodle-cron.sh
RUN chmod +x /usr/local/bin/moodle-cron.sh

# ─────────────────────────────────────────────
# 4. Entrypoint — always syncs config.php from
#    the image into the shared moodle_app volume.
#    This ensures redeploys always apply the latest
#    config.php, even if the volume already exists.
# ─────────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Moodle dataroot — not web-accessible
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata

WORKDIR /var/www/moodle

EXPOSE 9000

# Reports healthy once PHP-FPM config validates and the master process is up.
# moodle-web depends_on this being healthy before accepting traffic.
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php-fpm -t 2>&1 | grep -q "test is successful" || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]