#!/bin/sh
set -e

chown -R www-data:www-data var

# APP_SECRET signs sessions, remember-me cookies and CSRF tokens. In prod Symfony reads it from
# var/data/.app_secret (APP_SECRET_FILE): taken from $APP_SECRET if given, otherwise generated once
# and kept next to the database so it survives restarts.
SECRET_FILE=var/data/.app_secret
if [ -n "$APP_SECRET" ]; then
    printf '%s' "$APP_SECRET" > "$SECRET_FILE"
elif [ ! -s "$SECRET_FILE" ]; then
    php -r 'echo bin2hex(random_bytes(32));' > "$SECRET_FILE"
fi
chown www-data:www-data "$SECRET_FILE"
chmod 600 "$SECRET_FILE"

# Run console commands as www-data so the SQLite file and cache stay writable for PHP-FPM.
console() { su -s /bin/sh www-data -c "php bin/console $*"; }

console cache:warmup
console doctrine:migrations:migrate --no-interaction --allow-no-migration
console app:questions:import

exec docker-php-entrypoint "$@"
