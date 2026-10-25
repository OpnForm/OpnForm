#!/bin/sh

set -eu

ROOT_DIR=$(cd -- "$(dirname "$0")/../.." && pwd)
. "$ROOT_DIR/scripts/codex-worktree-lib.sh"

assert_php_version_supported() {
  version="$1"
  if ! php_version_is_supported "$version"; then
    echo "Expected PHP $version to be supported." >&2
    exit 1
  fi
}

assert_php_version_rejected() {
  version="$1"
  if php_version_is_supported "$version"; then
    echo "Expected PHP $version to be rejected." >&2
    exit 1
  fi
}

assert_php_version_rejected "8.2.29"
assert_php_version_supported "8.3.0"
assert_php_version_supported "8.4.3"
assert_php_version_rejected "8.5.0"
assert_php_version_rejected "9.0.0"
assert_php_version_rejected "invalid"

test_dir=$(mktemp -d)
trap 'rm -rf "$test_dir"' EXIT INT TERM
fake_php="$test_dir/php"

printf '%s\n' '#!/bin/sh' 'printf "%s" "$FAKE_PHP_VERSION"' >"$fake_php"
chmod +x "$fake_php"

FAKE_PHP_VERSION=8.4.3
export FAKE_PHP_VERSION
PATH="/usr/bin:/bin:$test_dir"
select_php_candidate "$fake_php"

[ "$CODEX_PHP_BIN" = "$fake_php" ]
[ "$CODEX_PHP_VERSION" = "8.4.3" ]
[ "${PATH%%:*}" = "$test_dir" ]

FAKE_PHP_VERSION=8.2.29
export FAKE_PHP_VERSION
if select_php_candidate "$fake_php"; then
  echo "Expected an incompatible PHP candidate to be rejected." >&2
  exit 1
fi

echo "Codex PHP runtime selection tests passed."

for version in 20.20.1 22.17.0 22.22.2 24.14.0 25.9.0 invalid; do
  if node_version_is_supported "$version"; then
    echo "Expected Node $version to be rejected." >&2
    exit 1
  fi
done
for version in 22.22.3 22.23.0 24.15.0 24.22.1 26.0.0; do
  node_version_is_supported "$version"
done

echo "Codex Node runtime selection tests passed."
