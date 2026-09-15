#!/bin/bash

set -euo pipefail

REPOSITORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEST_ROOT="$(mktemp -d)"
trap 'rm -rf "$TEST_ROOT"' EXIT INT TERM

prepare_repository() {
  local target="$1"

  mkdir -p "$target/api" "$target/client" "$target/scripts"
  cp "$REPOSITORY_ROOT/api/.env.docker" "$target/api/.env.docker"
  cp "$REPOSITORY_ROOT/api/.env.example" "$target/api/.env.example"
  cp "$REPOSITORY_ROOT/client/.env.docker" "$target/client/.env.docker"
  cp "$REPOSITORY_ROOT/client/.env.example" "$target/client/.env.example"
  cp "$REPOSITORY_ROOT/scripts/setup-env.sh" "$target/scripts/setup-env.sh"
}

env_value() {
  local file="$1"
  local key="$2"

  grep "^$key=" "$file" | tail -n 1 | cut -d= -f2-
}

assert_env_value() {
  local file="$1"
  local key="$2"
  local expected="$3"
  local actual

  actual="$(env_value "$file" "$key")"
  if [[ "$actual" != "$expected" ]]; then
    echo "Expected $key=$expected in $file, got $actual." >&2
    exit 1
  fi
}

fresh_root="$TEST_ROOT/fresh"
prepare_repository "$fresh_root"
(
  cd "$fresh_root"
  bash scripts/setup-env.sh --docker --public-url https://forms.example.com/
)

assert_env_value "$fresh_root/api/.env" APP_URL https://forms.example.com
assert_env_value "$fresh_root/api/.env" FRONT_URL https://forms.example.com
assert_env_value "$fresh_root/client/.env" NUXT_PUBLIC_APP_URL https://forms.example.com
assert_env_value "$fresh_root/client/.env" NUXT_PUBLIC_API_BASE https://forms.example.com/api
assert_env_value "$fresh_root/client/.env" NUXT_PRIVATE_API_BASE http://ingress/api

fresh_shared_secret="$(env_value "$fresh_root/api/.env" FRONT_API_SECRET)"
if [[ -z "$fresh_shared_secret" ]]; then
  echo "Expected setup to generate FRONT_API_SECRET." >&2
  exit 1
fi
assert_env_value "$fresh_root/client/.env" NUXT_API_SECRET "$fresh_shared_secret"

local_root="$TEST_ROOT/local"
prepare_repository "$local_root"
(
  cd "$local_root"
  bash scripts/setup-env.sh --docker >/dev/null
)

assert_env_value "$local_root/api/.env" APP_URL http://localhost
assert_env_value "$local_root/api/.env" FRONT_URL http://localhost
assert_env_value "$local_root/client/.env" NUXT_PUBLIC_APP_URL /
assert_env_value "$local_root/client/.env" NUXT_PUBLIC_API_BASE /api

generic_root="$TEST_ROOT/generic"
prepare_repository "$generic_root"
(
  cd "$generic_root"
  bash scripts/setup-env.sh >/dev/null
)

generic_shared_secret="$(env_value "$generic_root/api/.env" FRONT_API_SECRET)"
if [[ -z "$generic_shared_secret" ]]; then
  echo "Expected generic setup to generate FRONT_API_SECRET." >&2
  exit 1
fi
assert_env_value "$generic_root/client/.env" NUXT_API_SECRET "$generic_shared_secret"

existing_root="$TEST_ROOT/existing"
prepare_repository "$existing_root"
printf '%s\n' \
  'APP_KEY=base64:keep-existing-key' \
  'JWT_SECRET=keep-existing-jwt' \
  'APP_URL=http://old.example.test' \
  'FRONT_API_SECRET=keep-existing-shared-secret' \
  >"$existing_root/api/.env"
printf '%s\n' \
  'NUXT_PUBLIC_APP_URL=/' \
  'NUXT_PUBLIC_API_BASE=/api' \
  'NUXT_PRIVATE_API_BASE=http://ingress/api' \
  'NUXT_API_SECRET=keep-existing-shared-secret' \
  >"$existing_root/client/.env"

(
  cd "$existing_root"
  bash scripts/setup-env.sh --docker --public-url=https://new.example.test
)

assert_env_value "$existing_root/api/.env" APP_KEY base64:keep-existing-key
assert_env_value "$existing_root/api/.env" JWT_SECRET keep-existing-jwt
assert_env_value "$existing_root/api/.env" FRONT_API_SECRET keep-existing-shared-secret
assert_env_value "$existing_root/api/.env" APP_URL https://new.example.test
assert_env_value "$existing_root/api/.env" FRONT_URL https://new.example.test
assert_env_value "$existing_root/client/.env" NUXT_PUBLIC_APP_URL https://new.example.test
assert_env_value "$existing_root/client/.env" NUXT_PUBLIC_API_BASE https://new.example.test/api
assert_env_value "$existing_root/client/.env" NUXT_API_SECRET keep-existing-shared-secret

invalid_root="$TEST_ROOT/invalid"
prepare_repository "$invalid_root"
if (
  cd "$invalid_root"
  bash scripts/setup-env.sh --docker --public-url https://forms.example.com/path
); then
  echo "Expected a public URL containing a path to be rejected." >&2
  exit 1
fi

if [[ -e "$invalid_root/api/.env" || -e "$invalid_root/client/.env" ]]; then
  echo "Invalid input must be rejected before environment files are created." >&2
  exit 1
fi

invalid_port_root="$TEST_ROOT/invalid-port"
prepare_repository "$invalid_port_root"
if (
  cd "$invalid_port_root"
  bash scripts/setup-env.sh --docker --public-url https://forms.example.com:not-a-port
); then
  echo "Expected a non-numeric public URL port to be rejected." >&2
  exit 1
fi

empty_url_root="$TEST_ROOT/empty-url"
prepare_repository "$empty_url_root"
if (
  cd "$empty_url_root"
  bash scripts/setup-env.sh --docker --public-url=
); then
  echo "Expected an empty public URL to be rejected." >&2
  exit 1
fi

port_root="$TEST_ROOT/port"
prepare_repository "$port_root"
(
  cd "$port_root"
  bash scripts/setup-env.sh --docker --public-url http://127.0.0.1:8080 >/dev/null
)
assert_env_value "$port_root/api/.env" APP_URL http://127.0.0.1:8080

invalid_port_range_root="$TEST_ROOT/invalid-port-range"
prepare_repository "$invalid_port_range_root"
if (
  cd "$invalid_port_range_root"
  bash scripts/setup-env.sh --docker --public-url https://forms.example.com:65536
); then
  echo "Expected an out-of-range public URL port to be rejected." >&2
  exit 1
fi

docker_root="$TEST_ROOT/docker-wrapper"
prepare_repository "$docker_root"
cp "$REPOSITORY_ROOT/scripts/docker-setup.sh" "$docker_root/scripts/docker-setup.sh"
printf '%s\n' 'services: {}' >"$docker_root/docker-compose.yml"
mkdir -p "$docker_root/bin"
# The variables in the following strings belong to the generated fake Docker script.
# shellcheck disable=SC2016
printf '%s\n' \
  '#!/bin/bash' \
  'if [[ "$1" == "compose" && "$2" == "version" ]]; then exit 0; fi' \
  'if [[ "$1" == "compose" ]]; then printf "%s\n" "$*" >"$FAKE_DOCKER_ARGS"; exit 0; fi' \
  'exit 1' \
  >"$docker_root/bin/docker"
chmod +x "$docker_root/bin/docker"
FAKE_DOCKER_ARGS="$docker_root/docker-args"
export FAKE_DOCKER_ARGS
wrapper_output="$(
  cd "$docker_root"
  PATH="$docker_root/bin:$PATH" bash scripts/docker-setup.sh --public-url https://wrapper.example.test/
)"

assert_env_value "$docker_root/api/.env" APP_URL https://wrapper.example.test
assert_env_value "$docker_root/api/.env" FRONT_URL https://wrapper.example.test
assert_env_value "$docker_root/client/.env" NUXT_PUBLIC_APP_URL https://wrapper.example.test
assert_env_value "$docker_root/client/.env" NUXT_PUBLIC_API_BASE https://wrapper.example.test/api
grep -F 'compose -f docker-compose.yml up -d' "$FAKE_DOCKER_ARGS" >/dev/null
if [[ "$wrapper_output" != *'Then visit: https://wrapper.example.test'* ]]; then
  echo "Expected Docker setup to display the normalized public URL." >&2
  exit 1
fi

existing_wrapper_output="$(
  cd "$docker_root"
  PATH="$docker_root/bin:$PATH" bash scripts/docker-setup.sh
)"
if [[ "$existing_wrapper_output" != *'Then visit: https://wrapper.example.test'* ]]; then
  echo "Expected Docker setup to display the existing public URL when no new value is provided." >&2
  exit 1
fi

if bash "$REPOSITORY_ROOT/scripts/docker-setup.sh" --dev --public-url https://forms.example.com >/dev/null 2>&1; then
  echo "Expected --dev and --public-url to be rejected together." >&2
  exit 1
fi

bash -n "$REPOSITORY_ROOT/scripts/setup-env.sh"
bash -n "$REPOSITORY_ROOT/scripts/docker-setup.sh"

echo "Docker environment setup tests passed."
