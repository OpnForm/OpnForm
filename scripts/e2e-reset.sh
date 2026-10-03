#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
COMPOSE_FILES="-f $ROOT_DIR/docker-compose.e2e.yml"

docker compose $COMPOSE_FILES exec -T api sh -lc 'rm -rf storage/framework/cache/data/* storage/framework/sessions/*'
# The entrypoint and Composer can create files as root. Restore ownership, then
# run Artisan as the PHP-FPM user so seeded cache directories stay writable by API requests.
docker compose $COMPOSE_FILES exec -T api sh -lc 'mkdir -p storage/framework/cache/data storage/framework/sessions bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R u+rwX storage bootstrap/cache'
docker compose $COMPOSE_FILES exec -T --user www-data api php artisan optimize:clear
docker compose $COMPOSE_FILES exec -T --user www-data api php artisan cache:clear
docker compose $COMPOSE_FILES exec -T --user www-data api php artisan migrate:fresh --seed --seeder=Database\\Seeders\\E2ETestSeeder --force

echo "E2E database reset complete."
