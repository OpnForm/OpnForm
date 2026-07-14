#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

if [ ! -f "$CODEX_API_ENV_FILE" ] || [ ! -f "$CODEX_CLIENT_ENV_FILE" ]; then
  "$ROOT_DIR/scripts/codex-worktree-setup.sh"
  CODEX_ENV_SUMMARY_PRINTED=1
  export CODEX_ENV_SUMMARY_PRINTED
else
  write_codex_env_files
  ensure_php_dependencies
  ensure_node_dependencies
  ensure_codex_database
  if ! database_is_migrated; then
    "$ROOT_DIR/scripts/codex-worktree-reset-db.sh"
  fi
fi

print_codex_environment_summary

start_api() {
  if curl -fsS "$CODEX_API_HEALTH_URL" >/dev/null 2>&1; then
    if managed_server_is_running "$CODEX_STATE_DIR/api"; then
      echo "Reusing Codex Laravel server on $CODEX_API_BASE_URL"
      return 0
    fi

    echo "Port $CODEX_API_PORT responds to the OpnForm API but is not managed by this worktree." >&2
    exit 1
  fi

  if managed_server_is_running "$CODEX_STATE_DIR/api"; then
    wait_for_url "Codex Laravel server" "$CODEX_API_HEALTH_URL" "$CODEX_STATE_DIR/api.log"
    echo "Reusing Codex Laravel server on $CODEX_API_BASE_URL"
    return 0
  fi

  if port_is_listening "$CODEX_API_PORT"; then
    echo "Port $CODEX_API_PORT is already in use, but the Codex Laravel API is not responding." >&2
    exit 1
  fi

  if command -v screen >/dev/null 2>&1; then
    rm -f "$CODEX_STATE_DIR/api.pid"
    screen -dmS "$CODEX_API_SCREEN" sh -lc "cd '$ROOT_DIR/api' && echo \$\$ >'$CODEX_STATE_DIR/api.pid' && exec env RUNNER_TRACKING_ID= APP_ENV=codex php artisan serve --host='$CODEX_API_HOST' --port='$CODEX_API_PORT' --env=codex >'$CODEX_STATE_DIR/api.log' 2>&1"
    echo "$CODEX_API_SCREEN" >"$CODEX_STATE_DIR/api.screen"
  else
    rm -f "$CODEX_STATE_DIR/api.screen"
    (
      cd "$ROOT_DIR/api"
      exec nohup env RUNNER_TRACKING_ID= APP_ENV=codex php artisan serve --host="$CODEX_API_HOST" --port="$CODEX_API_PORT" --env=codex
    ) >"$CODEX_STATE_DIR/api.log" 2>&1 &
    echo "$!" >"$CODEX_STATE_DIR/api.pid"
  fi

  wait_for_url "Codex Laravel server" "$CODEX_API_HEALTH_URL" "$CODEX_STATE_DIR/api.log"
  echo "Started Codex Laravel server on $CODEX_API_BASE_URL"
}

start_client() {
  if curl -fsS "$CODEX_APP_HEALTH_URL" >/dev/null 2>&1; then
    if managed_server_is_running "$CODEX_STATE_DIR/client"; then
      echo "Reusing Codex Nuxt server on $CODEX_APP_URL"
      return 0
    fi

    echo "Port $CODEX_APP_PORT responds to the OpnForm UI but is not managed by this worktree." >&2
    exit 1
  fi

  if port_is_listening "$CODEX_APP_PORT"; then
    echo "Port $CODEX_APP_PORT is already in use, but the Codex Nuxt UI is not responding." >&2
    exit 1
  fi

  if command -v screen >/dev/null 2>&1; then
    rm -f "$CODEX_STATE_DIR/client.pid"
    screen -dmS "$CODEX_CLIENT_SCREEN" sh -c "cd '$ROOT_DIR/client' && set -a && . '$CODEX_CLIENT_ENV_FILE' && set +a && echo \$\$ >'$CODEX_STATE_DIR/client.pid' && exec env PATH='$PATH' RUNNER_TRACKING_ID= NUXT_HOST='$CODEX_APP_HOST' NUXT_PORT='$CODEX_APP_PORT' '$CODEX_NPM_BIN' run dev -- --host '$CODEX_APP_HOST' --port '$CODEX_APP_PORT' >'$CODEX_STATE_DIR/client.log' 2>&1"
    echo "$CODEX_CLIENT_SCREEN" >"$CODEX_STATE_DIR/client.screen"
  else
    rm -f "$CODEX_STATE_DIR/client.screen"
    (
      cd "$ROOT_DIR/client"
      set -a
      # shellcheck source=/dev/null
      . "$CODEX_CLIENT_ENV_FILE"
      set +a
      exec nohup env RUNNER_TRACKING_ID= NUXT_HOST="$CODEX_APP_HOST" NUXT_PORT="$CODEX_APP_PORT" "$CODEX_NPM_BIN" run dev -- --host "$CODEX_APP_HOST" --port "$CODEX_APP_PORT"
    ) >"$CODEX_STATE_DIR/client.log" 2>&1 &
    echo "$!" >"$CODEX_STATE_DIR/client.pid"
  fi

  wait_for_url "Codex Nuxt server" "$CODEX_APP_HEALTH_URL" "$CODEX_STATE_DIR/client.log"
  echo "Started Codex Nuxt server on $CODEX_APP_URL"
}

start_api
start_client

echo "Codex worktree app is ready:"
echo "  $CODEX_APP_URL"
