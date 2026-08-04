#!/bin/sh
set -e

cd /var/www/html

PORT="${PORT:-8080}"
export PORT

# Ensure Laravel storage structure exists
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Install dependencies if vendor is missing (e.g. local bind mounts)
if [ ! -f vendor/autoload.php ]; then
  composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader
fi

# Generate APP_KEY when Railway (or any host) has not provided one
if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php artisan key:generate --show)"
  echo "APP_KEY was not set — generated automatically for this container."
  echo "Persist it in Railway variables to keep sessions and encryption stable."
fi

# Production optimizations (safe for API + Sanctum)
if [ "${APP_ENV:-production}" != "local" ]; then
  php artisan config:cache --no-ansi || true
  php artisan route:cache --no-ansi || true
  php artisan view:cache --no-ansi || true
fi

# Nginx listens on Railway's dynamic PORT
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
