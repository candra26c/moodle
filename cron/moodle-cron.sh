#!/bin/sh
# moodle-cron.sh
# Runs Moodle's cron task every 60 seconds.
# This is a separate container — do NOT run this inside moodle-php.
#
# Moodle cron handles:
#   - Quiz auto-submission
#   - Grade recalculation
#   - Email notifications
#   - Scheduled backups
#   - Cache cleanup
#   - Course completion checks

echo "[moodle-cron] Starting Moodle cron runner..."

while true; do
    php /var/www/moodle/admin/cli/cron.php >> /proc/1/fd/1 2>&1
    sleep 60
done
