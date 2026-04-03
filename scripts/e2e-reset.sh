#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
COMPOSE_FILES="-f $ROOT_DIR/docker-compose.e2e.yml"

docker compose $COMPOSE_FILES exec -T api sh -lc 'rm -rf storage/framework/cache/data/* storage/framework/sessions/*'
docker compose $COMPOSE_FILES exec -T api php artisan optimize:clear
docker compose $COMPOSE_FILES exec -T api php artisan cache:clear
docker compose $COMPOSE_FILES exec -T api php artisan migrate:fresh --seed --seeder=Database\\Seeders\\E2ETestSeeder --force

echo "E2E database reset complete."
