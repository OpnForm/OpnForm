#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)

docker compose -f "$ROOT_DIR/docker-compose.e2e.yml" down -v --remove-orphans
