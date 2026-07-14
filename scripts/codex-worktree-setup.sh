#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

RESET_DB=0
for arg in "$@"; do
  case "$arg" in
    --reset-db) RESET_DB=1 ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

write_codex_env_files
ensure_php_dependencies
ensure_node_dependencies
ensure_codex_database
print_codex_environment_summary

if [ "$RESET_DB" = "1" ] || ! database_is_migrated; then
  "$ROOT_DIR/scripts/codex-worktree-reset-db.sh"
else
  echo "Reusing existing Codex worktree PostgreSQL database:"
  echo "  $CODEX_DB_NAME on $CODEX_DB_HOST:$CODEX_DB_PORT"
fi

echo "Codex worktree environment ready:"
echo "  API: $CODEX_API_BASE_URL"
echo "  UI:  $CODEX_APP_URL"
