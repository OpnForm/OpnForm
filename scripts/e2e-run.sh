#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)

"$ROOT_DIR/scripts/e2e-up.sh"
"$ROOT_DIR/scripts/e2e-reset.sh"

cd "$ROOT_DIR/client"
set -a
. "$ROOT_DIR/client/.env.e2e"
set +a
PLAYWRIGHT_BASE_URL="${PLAYWRIGHT_BASE_URL:-${NUXT_PUBLIC_APP_URL:-http://127.0.0.1:3100}}" npx playwright test "$@"
