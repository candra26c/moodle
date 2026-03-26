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

echo "[moodle-cron] $(date '+%Y-%m-%d %H:%M:%S') Starting Moodle cron runner..."

while true; do
    echo "[moodle-cron] $(date '+%Y-%m-%d %H:%M:%S') Running cron..."
    # timeout 300: kill the cron run if it hangs longer than 5 minutes,
    # preventing a stuck cron from blocking the next cycle indefinitely.
    timeout 300 php /var/www/moodle/admin/cli/cron.php >> /proc/1/fd/1 2>&1
    STATUS=$?
    if [ $STATUS -ne 0 ]; then
        echo "[moodle-cron] $(date '+%Y-%m-%d %H:%M:%S') WARNING: cron exited with status $STATUS"
    fi
    sleep 60
done
