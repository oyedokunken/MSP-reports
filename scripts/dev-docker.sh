#!/usr/bin/env bash

set -euo pipefail

ACTION="${1:-up}"

case "$ACTION" in
  up)
    docker compose up -d --build
    ;;
  down)
    docker compose down
    ;;
  clean)
    docker compose down --remove-orphans
    if docker ps -a --format '{{.Names}}' | grep -q '^reporting-gotenberg$'; then
      docker rm -f reporting-gotenberg >/dev/null
    fi
    ;;
  logs)
    docker compose logs -f --tail=200
    ;;
  ps)
    docker compose ps
    ;;
  restart)
    docker compose restart
    ;;
  *)
    echo "Usage: $0 {up|down|clean|logs|ps|restart}"
    exit 1
    ;;
esac
