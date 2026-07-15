#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

stop_screen_file "$CODEX_STATE_DIR/client.screen" "Codex Nuxt server" || true
stop_pid_file "$CODEX_STATE_DIR/client.pid" "Codex Nuxt server"
stop_checkout_server_on_port "$CODEX_APP_PORT" "Codex Nuxt server"

stop_screen_file "$CODEX_STATE_DIR/api.screen" "Codex Laravel server" || true
stop_pid_file "$CODEX_STATE_DIR/api.pid" "Codex Laravel server"
stop_checkout_server_on_port "$CODEX_API_PORT" "Codex Laravel server"
