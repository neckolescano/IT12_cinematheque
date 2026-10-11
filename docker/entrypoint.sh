#!/bin/sh
# Container start: listen on the host's $PORT, cache the configuration, bring the database up to date,
# load only the essential data, create the first Super Admin once, then start Apache.
set -e
cd /var/www/html

sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

php artisan migrate --force
php artisan db:seed --class=EssentialSeeder --force

# First deploy only: set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD, deploy, sign in, then delete both
# variables. --if-none does nothing once a Super Admin exists.
if [ -n "${INITIAL_ADMIN_EMAIL}" ] && [ -n "${INITIAL_ADMIN_PASSWORD}" ]; then
    php artisan staff:create-admin --if-none --no-interaction --email="${INITIAL_ADMIN_EMAIL}" || true
fi

chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
