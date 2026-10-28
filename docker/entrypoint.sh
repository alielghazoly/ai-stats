#!/bin/sh
set -e
cd /var/www/html

# فشل مبكر لو إعداد أساسي ناقص
for v in APP_KEY AI_SERVICE_TOKEN AI_DB_DATABASE AI_DB_USERNAME AI_DB_PASSWORD; do
    eval "val=\${$v}"
    if [ -z "$val" ]; then
        echo "ERROR: environment variable $v is not set (check your .env)" >&2
        exit 1
    fi
done

# اللوجز (ai_logs) في sqlite جوه الـ volume مش جوه الـ image
export DB_CONNECTION=sqlite
export DB_DATABASE=/var/www/html/storage/app/database.sqlite

if [ -z "${AI_API_KEY:-$ANTHROPIC_API_KEY}" ]; then
    echo "ERROR: set AI_API_KEY (or ANTHROPIC_API_KEY)" >&2
    exit 1
fi

mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
[ -f storage/app/database.sqlite ] || touch storage/app/database.sqlite
chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi
php artisan migrate --force
# الكاش في production بس (مع bind mount في الـ dev كان بيكتب ملفات root جوه المشروع)
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
