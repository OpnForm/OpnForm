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
