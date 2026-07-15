#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/.." && pwd)
CODEX_STATE_DIR="${CODEX_WORKTREE_STATE_DIR:-$ROOT_DIR/.e2e/codex}"
CODEX_API_ENV_FILE="$ROOT_DIR/api/.env.codex"
CODEX_CLIENT_ENV_FILE="$ROOT_DIR/client/.env.codex"
CODEX_ENV_FILE="$CODEX_STATE_DIR/env"

CODEX_HASH=$(printf '%s' "$ROOT_DIR" | cksum | awk '{ print $1 % 5000 }')
CODEX_COMPOSE_PROJECT="opnform-codex-$CODEX_HASH"
CODEX_DB_NAME="${CODEX_DB_NAME:-opnform_codex_$CODEX_HASH}"
CODEX_DB_USERNAME="${CODEX_DB_USERNAME:-opnform}"
CODEX_DB_PASSWORD="${CODEX_DB_PASSWORD:-opnform}"
CODEX_DB_HOST="${CODEX_DB_HOST:-127.0.0.1}"
CODEX_API_HOST="${CODEX_API_HOST:-127.0.0.1}"
CODEX_APP_HOST="${CODEX_APP_HOST:-127.0.0.1}"
CODEX_DB_PORT="${CODEX_DB_PORT:-$((20000 + CODEX_HASH))}"
CODEX_API_PORT="${CODEX_API_PORT:-$((26000 + CODEX_HASH))}"
CODEX_APP_PORT="${CODEX_APP_PORT:-$((32000 + CODEX_HASH))}"
CODEX_API_SCREEN="opnform-codex-api-$CODEX_HASH"
CODEX_CLIENT_SCREEN="opnform-codex-client-$CODEX_HASH"
CODEX_CLIENT_NODE_STAMP="$CODEX_STATE_DIR/client-node.version"

CODEX_API_BASE_URL="http://$CODEX_API_HOST:$CODEX_API_PORT"
CODEX_APP_URL="http://$CODEX_APP_HOST:$CODEX_APP_PORT"
CODEX_API_HEALTH_URL="$CODEX_API_BASE_URL/content/feature-flags"
CODEX_APP_HEALTH_URL="$CODEX_APP_URL/login"

export CODEX_COMPOSE_PROJECT CODEX_DB_NAME CODEX_DB_USERNAME CODEX_DB_PASSWORD
export CODEX_DB_HOST CODEX_DB_PORT CODEX_API_HOST CODEX_API_PORT CODEX_APP_HOST CODEX_APP_PORT
export CODEX_API_SCREEN CODEX_CLIENT_SCREEN

ensure_codex_state_dir() {
  mkdir -p "$CODEX_STATE_DIR"
}

codex_compose() {
  docker compose \
    --project-name "$CODEX_COMPOSE_PROJECT" \
    --file "$ROOT_DIR/docker-compose.codex.yml" \
    "$@"
}

ensure_docker_compose() {
  if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose v2 is required for the Codex worktree database." >&2
    exit 1
  fi

  if ! docker info >/dev/null 2>&1; then
    echo "Docker is installed but the daemon is not available." >&2
    exit 1
  fi
}

write_codex_env_files() {
  ensure_codex_state_dir

  cat >"$CODEX_API_ENV_FILE" <<EOF
APP_NAME="OpnForm Codex"
APP_ENV=codex
APP_DEBUG=true
APP_LOG_LEVEL=debug
APP_URL=$CODEX_API_BASE_URL
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEV_CORS=true

LOG_CHANNEL=stack
LOG_LEVEL=debug

FRONT_URL=$CODEX_APP_URL
FRONT_API_SECRET=secret

DB_CONNECTION=pgsql
DB_HOST=$CODEX_DB_HOST
DB_PORT=$CODEX_DB_PORT
DB_DATABASE=$CODEX_DB_NAME
DB_USERNAME=$CODEX_DB_USERNAME
DB_PASSWORD=$CODEX_DB_PASSWORD

FILESYSTEM_DRIVER=local
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=log
CACHE_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=log
MAIL_FROM_ADDRESS=dev@localhost
MAIL_FROM_NAME="OpnForm Codex"

JWT_TTL=10080
JWT_REMEMBER_TTL=43200
JWT_SECRET=38hjE96gYu4ceKSSHNnzgeynMr2m3WkNBN2u7zzpVeiITtxFEloxAjcr7TCKuiiy
JWT_SKIP_IP_UA_VALIDATION=true

SELF_HOSTED=false
ZAPIER_ENABLED=false
ADMIN_EMAILS=e2e@example.test
EOF

  cat >"$CODEX_CLIENT_ENV_FILE" <<EOF
NUXT_LOG_LEVEL=info
NUXT_PUBLIC_APP_URL=$CODEX_APP_URL
NUXT_PUBLIC_API_BASE=$CODEX_API_BASE_URL
NUXT_PRIVATE_API_BASE=$CODEX_API_BASE_URL
NUXT_API_SECRET=secret
NUXT_PUBLIC_ENV=codex
NUXT_PUBLIC_CODEX_AUTO_LOGIN=true
NUXT_PUBLIC_CODEX_AUTO_LOGIN_EMAIL=e2e@example.test
NUXT_PUBLIC_CODEX_AUTO_LOGIN_PASSWORD=Abcd@1234
EOF

  cat >"$CODEX_ENV_FILE" <<EOF
CODEX_STATE_DIR=$CODEX_STATE_DIR
CODEX_COMPOSE_PROJECT=$CODEX_COMPOSE_PROJECT
CODEX_DB_NAME=$CODEX_DB_NAME
CODEX_DB_HOST=$CODEX_DB_HOST
CODEX_DB_PORT=$CODEX_DB_PORT
CODEX_API_HOST=$CODEX_API_HOST
CODEX_API_PORT=$CODEX_API_PORT
CODEX_APP_HOST=$CODEX_APP_HOST
CODEX_APP_PORT=$CODEX_APP_PORT
CODEX_API_BASE_URL=$CODEX_API_BASE_URL
CODEX_APP_URL=$CODEX_APP_URL
CODEX_API_HEALTH_URL=$CODEX_API_HEALTH_URL
CODEX_APP_HEALTH_URL=$CODEX_APP_HEALTH_URL
EOF
}

print_codex_environment_summary() {
  if [ "${CODEX_ENV_SUMMARY_PRINTED:-0}" = "1" ]; then
    return 0
  fi

  echo "Codex worktree isolation:"
  echo "  Checkout: $ROOT_DIR"
  echo "  PostgreSQL/API/UI ports: $CODEX_DB_PORT / $CODEX_API_PORT / $CODEX_APP_PORT"
  echo "  Compose project: $CODEX_COMPOSE_PROJECT"
  echo "  State directory: $CODEX_STATE_DIR"
  echo "  Sibling worktrees use different ports, database volumes, and API bases."

  CODEX_ENV_SUMMARY_PRINTED=1
  export CODEX_ENV_SUMMARY_PRINTED
}

ensure_php_dependencies() {
  if [ ! -f "$ROOT_DIR/api/vendor/autoload.php" ]; then
    (
      cd "$ROOT_DIR/api"
      composer install --no-interaction --prefer-dist
    )
  fi
}

remove_node_modules() {
  target="$ROOT_DIR/client/node_modules"
  attempt=0

  while [ -e "$target" ] && [ "$attempt" -lt 5 ]; do
    rm -rf "$target" >/dev/null 2>&1 || true
    attempt=$((attempt + 1))

    if [ -e "$target" ]; then
      sleep 1
    fi
  done

  if [ -e "$target" ]; then
    echo "Could not remove stale client/node_modules after $attempt attempts." >&2
    exit 1
  fi
}

node_version_is_supported() {
  version="$1"
  major=${version%%.*}
  remainder=${version#*.}
  minor=${remainder%%.*}

  case "$major" in
    20) [ "$minor" -ge 11 ] ;;
    22|24) return 0 ;;
    *) return 1 ;;
  esac
}

select_node_candidate() {
  candidate="$1"

  [ -n "$candidate" ] && [ -x "$candidate" ] || return 1

  version=$("$candidate" -p 'process.versions.node' 2>/dev/null || true)
  node_version_is_supported "$version" || return 1

  node_dir=$(dirname "$candidate")
  npm_candidate="$node_dir/npm"
  [ -x "$npm_candidate" ] || return 1

  case ":$PATH:" in
    *":$node_dir:"*) ;;
    *) PATH="$node_dir:$PATH" ;;
  esac

  npm_version=$("$npm_candidate" --version 2>/dev/null || true)
  [ -n "$npm_version" ] || return 1

  CODEX_NODE_BIN="$candidate"
  CODEX_NPM_BIN="$npm_candidate"
  CODEX_NODE_VERSION="$version"
  export PATH CODEX_NODE_BIN CODEX_NPM_BIN CODEX_NODE_VERSION
  return 0
}

select_node_runtime() {
  if [ -n "${CODEX_NODE_BIN:-}" ]; then
    if select_node_candidate "$CODEX_NODE_BIN"; then
      return 0
    fi

    echo "CODEX_NODE_BIN must point to a supported Node 20.11+, 22.x, or 24.x runtime with npm." >&2
    exit 1
  fi

  herd_node="$HOME/Library/Application Support/Herd/config/nvm/versions/node/v20.20.1/bin/node"
  bundled_node="$HOME/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node"
  path_node=$(command -v node 2>/dev/null || true)

  for candidate in "$herd_node" "$bundled_node" "$path_node"; do
    if select_node_candidate "$candidate"; then
      return 0
    fi
  done

  echo "A supported Node runtime (20.11+, 22.x, or 24.x) with its matching npm is required." >&2
  exit 1
}

ensure_node_dependencies() {
  select_node_runtime
  node_version="v$CODEX_NODE_VERSION"

  install_reason=""
  if [ ! -d "$ROOT_DIR/client/node_modules" ]; then
    install_reason="missing node_modules"
  elif [ ! -x "$ROOT_DIR/client/node_modules/.bin/nuxt" ]; then
    install_reason="Nuxt binary missing"
  elif [ ! -f "$CODEX_CLIENT_NODE_STAMP" ]; then
    install_reason="Node runtime stamp missing"
  elif [ "$(cat "$CODEX_CLIENT_NODE_STAMP" 2>/dev/null || true)" != "$node_version" ]; then
    install_reason="Node runtime changed"
  fi

  if [ -n "$install_reason" ]; then
    echo "Installing client dependencies with Node $node_version and npm $("$CODEX_NPM_BIN" --version) ($install_reason)."
    if [ -d "$ROOT_DIR/client/node_modules" ]; then
      remove_node_modules
    fi

    (
      cd "$ROOT_DIR/client"
      "$CODEX_NPM_BIN" ci --no-audit --no-fund
    )
  fi

  ensure_codex_state_dir
  printf '%s\n' "$node_version" >"$CODEX_CLIENT_NODE_STAMP"
}

port_is_listening() {
  port="$1"
  lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
}

wait_for_url() {
  label="$1"
  url="$2"
  log_file="$3"
  attempt=0

  until curl -fsS "$url" >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 90 ]; then
      echo "$label did not become ready in time: $url" >&2
      if [ -f "$log_file" ]; then
        tail -n 120 "$log_file" >&2 || true
      fi
      exit 1
    fi
    sleep 2
  done
}

wait_for_database() {
  attempt=0

  until codex_compose exec -T db pg_isready -U "$CODEX_DB_USERNAME" -d "$CODEX_DB_NAME" >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 45 ]; then
      echo "Codex PostgreSQL database did not become ready in time." >&2
      codex_compose ps >&2 || true
      codex_compose logs --tail=120 db >&2 || true
      exit 1
    fi
    sleep 2
  done
}

ensure_codex_database() {
  ensure_docker_compose
  codex_compose up -d db
  wait_for_database
}

database_is_migrated() {
  codex_compose exec -T db \
    psql -U "$CODEX_DB_USERNAME" -d "$CODEX_DB_NAME" -tAc \
    "SELECT to_regclass('public.migrations') IS NOT NULL" 2>/dev/null \
    | tr -d '[:space:]' \
    | grep -qx 't'
}

screen_session_is_running() {
  file="$1"

  if [ ! -f "$file" ] || ! command -v screen >/dev/null 2>&1; then
    return 1
  fi

  screen_name=$(cat "$file" 2>/dev/null || true)
  [ -n "$screen_name" ] && screen -ls | grep -q "\\.${screen_name}[[:space:]]"
}

pid_file_is_running() {
  file="$1"

  if [ ! -f "$file" ]; then
    return 1
  fi

  pid=$(cat "$file" 2>/dev/null || true)
  case "$pid" in
    ''|*[!0-9]*) return 1 ;;
  esac

  kill -0 "$pid" >/dev/null 2>&1
}

managed_server_is_running() {
  screen_session_is_running "$1.screen" || pid_file_is_running "$1.pid"
}

stop_pid_file() {
  file="$1"
  label="$2"

  if [ ! -f "$file" ]; then
    return 0
  fi

  pid=$(cat "$file" 2>/dev/null || true)
  rm -f "$file"

  case "$pid" in
    ''|*[!0-9]*) return 0 ;;
  esac

  if ! kill -0 "$pid" >/dev/null 2>&1; then
    return 0
  fi

  pkill -TERM -P "$pid" >/dev/null 2>&1 || true
  kill "$pid" >/dev/null 2>&1 || true
  sleep 1

  if kill -0 "$pid" >/dev/null 2>&1; then
    pkill -KILL -P "$pid" >/dev/null 2>&1 || true
    kill -KILL "$pid" >/dev/null 2>&1 || true
  fi

  echo "Stopped $label."
}

stop_screen_file() {
  file="$1"
  label="$2"

  if [ ! -f "$file" ]; then
    return 1
  fi

  screen_name=$(cat "$file" 2>/dev/null || true)
  rm -f "$file"

  if [ -z "$screen_name" ] || ! command -v screen >/dev/null 2>&1; then
    return 1
  fi

  screen -S "$screen_name" -X quit >/dev/null 2>&1 || true
  echo "Stopped $label."
  return 0
}

stop_checkout_server_on_port() {
  port="$1"
  label="$2"
  pids=$(lsof -nP -t -iTCP:"$port" -sTCP:LISTEN 2>/dev/null || true)
  stopped=0

  for pid in $pids; do
    command=$(ps -p "$pid" -o command= 2>/dev/null || true)
    process_cwd=$(lsof -a -p "$pid" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p' | head -n 1 || true)
    case "$command:$process_cwd" in
      *"$ROOT_DIR"*)
          kill -TERM "$pid" >/dev/null 2>&1 || true
          stopped=1
          ;;
      *) ;;
    esac
  done

  if [ "$stopped" = "1" ]; then
    sleep 1
    for pid in $pids; do
      if kill -0 "$pid" >/dev/null 2>&1; then
        kill -KILL "$pid" >/dev/null 2>&1 || true
      fi
    done
    echo "Stopped orphaned $label process on port $port."
  fi
}
