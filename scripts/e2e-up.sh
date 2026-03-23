#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
COMPOSE_FILES="-f $ROOT_DIR/docker-compose.e2e.yml"
UI_LOG_FILE="$ROOT_DIR/.e2e-ui.log"

docker compose $COMPOSE_FILES up -d db api ingress

if ! docker compose $COMPOSE_FILES exec -T api sh -lc 'command -v composer >/dev/null 2>&1'; then
  docker compose $COMPOSE_FILES exec -T api sh -lc "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
fi

docker compose $COMPOSE_FILES exec -T api composer install --no-interaction --prefer-dist
docker compose $COMPOSE_FILES exec -T api php artisan optimize:clear

attempt=0
until docker compose $COMPOSE_FILES exec -T api php artisan --version >/dev/null 2>&1; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 90 ]; then
  echo "Laravel E2E stack did not become ready in time." >&2
    docker compose $COMPOSE_FILES ps >&2
    docker compose $COMPOSE_FILES logs --tail=200 api ingress >&2
    exit 1
  fi
  sleep 2
done

cd "$ROOT_DIR/client"
if [ ! -d node_modules ]; then
  npm ci
fi

set -a
. "$ROOT_DIR/client/.env.e2e"
set +a
E2E_APP_URL="${NUXT_PUBLIC_APP_URL:-http://127.0.0.1:3100}"

if [ "${FORCE_E2E_BUILD:-0}" = "1" ] || [ ! -f .output/server/index.mjs ]; then
  rm -rf .output
  npm run build:e2e >"$UI_LOG_FILE" 2>&1
  echo "E2E backend is ready at http://127.0.0.1:8089 and the Nuxt app has been built for Playwright on $E2E_APP_URL"
else
  : >"$UI_LOG_FILE"
  echo "E2E backend is ready at http://127.0.0.1:8089 and reusing the existing Nuxt build for Playwright on $E2E_APP_URL"
fi
