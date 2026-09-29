#!/bin/sh
set -e

# Setup Laravel directories
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

touch /var/www/html/storage/logs/laravel.log

# Discover installed production packages
php /var/www/html/artisan package:discover --ansi || true

# Run migrations if enabled
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force
fi

# Cache config, routes, and views in production if requested
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing Laravel for production..."
    php /var/www/html/artisan config:cache || true
    php /var/www/html/artisan route:cache || true
    php /var/www/html/artisan view:cache || true
fi

# Ensure correct permissions for www-data after any artisan execution
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Setup cron for Laravel schedule
mkdir -p /etc/crontabs
echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/crontabs/www-data
chmod 0600 /etc/crontabs/www-data

# Execute main container command (Supervisord)
exec "$@"
