#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

"$ROOT_DIR/scripts/codex-worktree-down.sh"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  codex_compose down --volumes --remove-orphans
fi

rm -rf "$CODEX_STATE_DIR"
echo "Destroyed Codex worktree services and PostgreSQL volume."
