#!/bin/sh
# docker-entrypoint.sh
# Always copies config.php from the image (/etc/moodle/config.php)
# into the shared moodle_app volume (/var/www/moodle/config.php).
#
# This ensures that every redeploy applies the latest config.php,
# even if the moodle_app volume already exists from a previous deploy.

set -e

echo "[entrypoint] Syncing config.php from image into volume..."
cp /etc/moodle/config.php /var/www/moodle/config.php
chown www-data:www-data /var/www/moodle/config.php
echo "[entrypoint] config.php synced."

echo "[entrypoint] Fixing moodledata permissions..."
mkdir -p /var/www/moodledata
chown -R www-data:www-data /var/www/moodledata
chmod -R 0755 /var/www/moodledata
echo "[entrypoint] moodledata permissions fixed."

exec "$@"