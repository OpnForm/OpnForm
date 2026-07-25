#!/bin/sh

set -eu

REPOSITORY_ROOT=$(cd -- "$(dirname "$0")/../.." && pwd)
. "$REPOSITORY_ROOT/scripts/codex-worktree-lib.sh"

test_dir=$(mktemp -d)
trap 'rm -rf "$test_dir"' EXIT INT TERM

ROOT_DIR="$test_dir/repository"
CODEX_STATE_DIR="$test_dir/state"
CODEX_API_ENV_FILE="$ROOT_DIR/api/.env.codex"
CODEX_CLIENT_ENV_FILE="$ROOT_DIR/client/.env.codex"
CODEX_ENV_FILE="$CODEX_STATE_DIR/env"
CODEX_CLIENT_DEPENDENCY_STAMP="$CODEX_STATE_DIR/client-dependencies.sha256"
CODEX_CLIENT_INSTALL_LOCK="$CODEX_STATE_DIR/client-install.lock"
CODEX_CLIENT_NUXT_DIR="$CODEX_STATE_DIR/client/nuxt"
CODEX_CLIENT_VITE_CACHE_DIR="$CODEX_STATE_DIR/client/vite"

fake_bin="$test_dir/bin"
fake_node="$fake_bin/node"
fake_npm="$fake_bin/npm"
install_count_file="$test_dir/npm-ci.count"

mkdir -p "$ROOT_DIR/api" "$ROOT_DIR/client" "$fake_bin"
printf '%s\n' '{"lockfileVersion":3,"packages":{}}' >"$ROOT_DIR/client/package-lock.json"

printf '%s\n' \
  '#!/bin/sh' \
  'printf "%s" "$FAKE_NODE_VERSION"' \
  >"$fake_node"

printf '%s\n' \
  '#!/bin/sh' \
  'if [ "$1" = "--version" ]; then' \
  '  echo "11.0.0"' \
  '  exit 0' \
  'fi' \
  'if [ "$1" != "ci" ]; then' \
  '  echo "Unexpected npm command: $*" >&2' \
  '  exit 1' \
  'fi' \
  'count=$(cat "$FAKE_NPM_COUNT_FILE" 2>/dev/null || echo 0)' \
  'sleep "${FAKE_NPM_SLEEP:-0}"' \
  'printf "%s\n" "$((count + 1))" >"$FAKE_NPM_COUNT_FILE"' \
  'mkdir -p "$FAKE_CLIENT_DIR/node_modules/.bin" "$NUXT_BUILD_DIR" "$NUXT_VITE_CACHE_DIR"' \
  'printf "%s\n" "#!/bin/sh" "exit 0" >"$FAKE_CLIENT_DIR/node_modules/.bin/nuxt"' \
  'chmod +x "$FAKE_CLIENT_DIR/node_modules/.bin/nuxt"' \
  >"$fake_npm"

chmod +x "$fake_node" "$fake_npm"

FAKE_NODE_VERSION=24.10.0
FAKE_NPM_COUNT_FILE="$install_count_file"
FAKE_CLIENT_DIR="$ROOT_DIR/client"
CODEX_NODE_BIN="$fake_node"
export FAKE_NODE_VERSION FAKE_NPM_COUNT_FILE FAKE_CLIENT_DIR CODEX_NODE_BIN

write_codex_env_files
grep -F "NUXT_BUILD_DIR=\"$CODEX_CLIENT_NUXT_DIR\"" "$CODEX_CLIENT_ENV_FILE" >/dev/null
grep -F "NUXT_VITE_CACHE_DIR=\"$CODEX_CLIENT_VITE_CACHE_DIR\"" "$CODEX_CLIENT_ENV_FILE" >/dev/null

ensure_node_dependencies
[ "$(cat "$install_count_file")" = "1" ]
[ -f "$CODEX_CLIENT_DEPENDENCY_STAMP" ]

printf '%s\n' "keep" >"$CODEX_CLIENT_NUXT_DIR/restart-sentinel"
printf '%s\n' "keep" >"$CODEX_CLIENT_VITE_CACHE_DIR/restart-sentinel"
ensure_node_dependencies
[ "$(cat "$install_count_file")" = "1" ]
[ -f "$CODEX_CLIENT_NUXT_DIR/restart-sentinel" ]
[ -f "$CODEX_CLIENT_VITE_CACHE_DIR/restart-sentinel" ]

printf '%s\n' '{"lockfileVersion":3,"packages":{"changed":{}}}' >"$ROOT_DIR/client/package-lock.json"
ensure_node_dependencies
[ "$(cat "$install_count_file")" = "2" ]
[ ! -e "$CODEX_CLIENT_NUXT_DIR/restart-sentinel" ]
[ ! -e "$CODEX_CLIENT_VITE_CACHE_DIR/restart-sentinel" ]

FAKE_NODE_VERSION=22.14.0
export FAKE_NODE_VERSION
ensure_node_dependencies
[ "$(cat "$install_count_file")" = "3" ]

printf '%s\n' '{"lockfileVersion":3,"packages":{"concurrent":{}}}' >"$ROOT_DIR/client/package-lock.json"
FAKE_NPM_SLEEP=1
export FAKE_NPM_SLEEP
ensure_node_dependencies &
first_install_pid=$!
ensure_node_dependencies &
second_install_pid=$!
wait "$first_install_pid"
wait "$second_install_pid"

[ "$(cat "$install_count_file")" = "4" ]
[ ! -e "$CODEX_CLIENT_INSTALL_LOCK" ]

printf '%s\n' '{"lockfileVersion":3,"packages":{"stale-lock":{}}}' >"$ROOT_DIR/client/package-lock.json"
mkdir "$CODEX_CLIENT_INSTALL_LOCK"
printf '%s\n' "2147483647" >"$CODEX_CLIENT_INSTALL_LOCK/pid"
FAKE_NPM_SLEEP=0
export FAKE_NPM_SLEEP
ensure_node_dependencies

[ "$(cat "$install_count_file")" = "5" ]
[ ! -e "$CODEX_CLIENT_INSTALL_LOCK" ]

echo "Codex client lifecycle tests passed."
