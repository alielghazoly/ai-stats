#!/bin/sh
set -e
cd /var/www/html

# Fail early if a required setting is missing
for v in APP_KEY AI_SERVICE_TOKEN AI_DB_DATABASE AI_DB_USERNAME AI_DB_PASSWORD; do
    eval "val=\${$v}"
    if [ -z "$val" ]; then
        echo "ERROR: environment variable $v is not set (check your .env)" >&2
        exit 1
    fi
done

# Logs (ai_logs) live in sqlite inside the volume, not inside the image
export DB_CONNECTION=sqlite
export DB_DATABASE=/var/www/html/storage/app/database.sqlite

if [ -z "${AI_API_KEY:-$ANTHROPIC_API_KEY}" ]; then
    echo "ERROR: set AI_API_KEY (or ANTHROPIC_API_KEY)" >&2
    exit 1
fi

mkdir -p storage/app/exports storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
[ -f storage/app/database.sqlite ] || touch storage/app/database.sqlite
chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi
php artisan migrate --force
# Cache in production only (with the dev bind mount it wrote root-owned files into the project)
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
