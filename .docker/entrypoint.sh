#!/usr/bin/env sh

# Dump environment variables to .env file for Laravel (needed for cron/scheduler)
printenv | grep -v -e '^PATH=' -e '^HOME=' -e '^USER=' -e '^SHELL=' | grep -E '^[a-zA-Z0-9_]+=' | sed 's/=\(.*\)/="\1"/' > /var/www/html/.env
chown www-data:www-data /var/www/html/.env

# Run user scripts, if they exist
for f in /var/www/html/.fly/scripts/*.sh; do
    # Bail out this loop if any script exits with non-zero status code
    bash "$f" -e
done

if [ $# -gt 0 ]; then
    # If we passed a command, run it as root
    exec "$@"
else
    exec supervisord -c /etc/supervisor/supervisord.conf
fi