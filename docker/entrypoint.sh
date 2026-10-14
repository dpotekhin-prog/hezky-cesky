#!/bin/sh
set -e

# Run console commands as www-data so the SQLite file and cache stay writable for Apache.
console() { su -s /bin/sh www-data -c "php bin/console $*"; }

chown -R www-data:www-data var
console cache:warmup
console doctrine:migrations:migrate --no-interaction --allow-no-migration
console app:questions:import

exec docker-php-entrypoint "$@"
