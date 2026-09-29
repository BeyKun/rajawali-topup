#!/bin/sh
set -e

# Ensure essential directories exist with proper permissions
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/app/private \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Remove any stale dev cache files from host machine
rm -f /var/www/html/bootstrap/cache/packages.php \
      /var/www/html/bootstrap/cache/services.php \
      /var/www/html/bootstrap/cache/config.php \
      /var/www/html/bootstrap/cache/routes-v7.php \
      /var/www/html/bootstrap/cache/events.php

touch /var/www/html/storage/logs/laravel.log
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

php artisan package:discover --ansi || true

# Create storage symlink if not already created
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link --force || true
fi

# Run database migrations if enabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ] || [ "${RUN_MIGRATIONS:-true}" = "1" ]; then
    echo "==> [ENTRYPOINT] Checking database connection and running migrations..."
    max_retries=10
    retry_count=0
    migration_success=0

    while [ $retry_count -lt $max_retries ]; do
        if php artisan migrate --force; then
            echo "==> [ENTRYPOINT] Database migrations completed successfully."
            migration_success=1
            break
        fi
        retry_count=$((retry_count + 1))
        echo "==> [ENTRYPOINT] Migration failed or database not ready yet. Retrying in 3 seconds ($retry_count/$max_retries)..."
        sleep 3
    done

    if [ $migration_success -ne 1 ]; then
        echo "==> [ENTRYPOINT] WARNING: Database migration could not be completed after $max_retries attempts."
    fi
fi

# Optimize Laravel configuration, routes, and views in production
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "Caching configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

# Re-ensure permissions for www-data after artisan commands
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
