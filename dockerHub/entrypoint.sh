#!/bin/sh

mkdir -p storage/framework/views
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan storage:link

exec php-fpm -F
