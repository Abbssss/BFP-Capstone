#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i -E "s/^Listen [0-9]+/Listen ${port}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:([0-9]+)>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"

php artisan storage:link --force
php artisan migrate --force

exec apache2-foreground
