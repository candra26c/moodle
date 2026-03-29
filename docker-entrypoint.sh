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

# ─────────────────────────────────────────────
# Ensure moodledata and all required subdirectories exist
# with correct ownership and permissions (02777 = setgid + rwxrwxrwx).
# Moodle expects these to pre-exist; missing dirs cause permission errors.
# ─────────────────────────────────────────────
for dir in \
    /var/www/moodledata \
    /var/www/moodledata/temp \
    /var/www/moodledata/cache \
    /var/www/moodledata/localcache \
    /var/www/moodledata/sessions \
    /var/www/moodledata/backuptemp \
    /tmp/moodlerequest
do
    if [ ! -d "$dir" ]; then
        echo "[entrypoint] Creating $dir..."
        mkdir -p "$dir"
        chown www-data:www-data "$dir"
        chmod 02777 "$dir"
    fi
done

# ─────────────────────────────────────────────
# localcachedir lives on tmpfs (/var/cache/moodle_localcache).
# tmpfs is mounted fresh each container start, so always recreate.
# ─────────────────────────────────────────────
mkdir -p /var/cache/moodle_localcache
chown www-data:www-data /var/cache/moodle_localcache
chmod 02777 /var/cache/moodle_localcache
echo "[entrypoint] localcachedir ready."

# ─────────────────────────────────────────────
# Only run expensive recursive chown on moodledata when owner is wrong.
# Avoids slowdowns as moodledata grows with student uploads over time.
# ─────────────────────────────────────────────
if [ "$(stat -c '%U' /var/www/moodledata)" != "www-data" ]; then
    echo "[entrypoint] moodledata owner mismatch — fixing permissions..."
    chown -R www-data:www-data /var/www/moodledata
    chmod 02777 /var/www/moodledata
    echo "[entrypoint] moodledata permissions fixed."
else
    echo "[entrypoint] moodledata permissions OK."
fi

exec "$@"
