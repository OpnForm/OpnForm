#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

"$ROOT_DIR/scripts/codex-worktree-up.sh"
"$ROOT_DIR/scripts/codex-worktree-reset-db.sh"
select_node_runtime

cd "$ROOT_DIR/client"
PLAYWRIGHT_NO_WEB_SERVER=1 \
PLAYWRIGHT_DEV_SERVER=1 \
PLAYWRIGHT_BASE_URL="$CODEX_APP_URL" \
PLAYWRIGHT_API_BASE_URL="$CODEX_API_BASE_URL" \
"$CODEX_NPM_BIN" exec -- playwright test "$@"
