#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

write_codex_env_files
ensure_php_dependencies
ensure_codex_database

rm -rf "$ROOT_DIR/api/storage/framework/cache/data"/* "$ROOT_DIR/api/storage/framework/sessions"/*

(
  cd "$ROOT_DIR/api"
  APP_ENV=codex php artisan optimize:clear --env=codex
  APP_ENV=codex php artisan migrate:fresh --seed --seeder=Database\\Seeders\\E2ETestSeeder --force --env=codex
)

echo "Codex worktree PostgreSQL database reset:"
echo "  $CODEX_DB_NAME on $CODEX_DB_HOST:$CODEX_DB_PORT"
