#!/bin/bash

set -euo pipefail

# Configurable parameters (override via env or flags)
# Defaults tailored for this environment
SOURCE_DIR=${SOURCE:-/home/kenny/reporting}
TARGET_DIR=${TARGET:-/root/reporting}
CONTAINER_NAME=${CONTAINER:-reporting-app}
RUN_PERMS=${RUN_PERMS:-1} # set to 0 to skip permission fixes

usage() {
  cat <<EOF
Usage: $(basename "$0") [--source PATH] [--target PATH] [--container NAME] [--skip-perms]

Environment overrides:
  SOURCE=/home/kenny/reporting    # host source path (default)
  TARGET=/root/reporting          # container bind mount target (default)
  CONTAINER=reporting-app         # app container name (default)

Flags:
  --source PATH       Override source directory
  --target PATH       Override target directory
  --container NAME    Override container name
  --skip-perms        Skip permission fix inside container
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source) SOURCE_DIR="$2"; shift 2 ;;
    --target) TARGET_DIR="$2"; shift 2 ;;
    --container) CONTAINER_NAME="$2"; shift 2 ;;
    --skip-perms) RUN_PERMS=0; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1"; usage; exit 1 ;;
  esac
done

# Determine docker command (add sudo if necessary)
DOCKER_CMD=${DOCKER_CMD:-docker}
if ! $DOCKER_CMD ps >/dev/null 2>&1; then
  if sudo -n $DOCKER_CMD ps >/dev/null 2>&1; then
    DOCKER_CMD="sudo $DOCKER_CMD"
  else
    echo "❌ Cannot access Docker daemon. Try running with sudo or add your user to the docker group."
    exit 1
  fi
fi

echo "🔧 Settings"
echo "   SOURCE   : $SOURCE_DIR"
echo "   TARGET   : $TARGET_DIR"
echo "   CONTAINER: $CONTAINER_NAME"
echo ""

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "❌ Source directory not found: $SOURCE_DIR"
  exit 1
fi

echo "🔄 Syncing $SOURCE_DIR -> $TARGET_DIR ..."

RSYNC_OPTS=(
  -av --delete
  --exclude 'node_modules'
  --exclude 'vendor'
  --exclude '.git'
  --exclude 'storage/logs/*'
  --exclude 'storage/framework/cache/*'
  --exclude 'storage/framework/sessions/*'
  --exclude 'storage/framework/views/*'
)

# Use sudo for rsync if needed (common for syncing into /root target)
if [[ -w "$(dirname "$TARGET_DIR")" ]]; then
  rsync "${RSYNC_OPTS[@]}" "$SOURCE_DIR/" "$TARGET_DIR/"
else
  sudo rsync "${RSYNC_OPTS[@]}" "$SOURCE_DIR/" "$TARGET_DIR/"
fi

echo "✅ Sync complete!"
echo ""

if (( RUN_PERMS )); then
  echo "🔧 Fixing permissions and creating directories inside container..."
  $DOCKER_CMD exec -u root "$CONTAINER_NAME" bash -lc '
    set -e
    mkdir -p /var/www/html/storage/app/reports
    mkdir -p /var/www/html/storage/logs
    mkdir -p /var/www/html/storage/framework/{cache,sessions,views}

    touch /var/www/html/storage/logs/laravel.log

    chown -R www:www /var/www/html/storage
    chmod -R 775 /var/www/html/storage
    chown -R www:www /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/bootstrap/cache
  '
  echo "✅ Permissions fixed!"
  echo ""

  echo "📋 Suggested next steps:"
  echo "   $DOCKER_CMD exec $CONTAINER_NAME php artisan config:clear"
  echo "   $DOCKER_CMD exec $CONTAINER_NAME php artisan cache:clear"
  echo "   $DOCKER_CMD exec -u root $CONTAINER_NAME supervisorctl restart all"
else
  echo "⏭️  Skipped permission fixes (per --skip-perms)"
fi

echo "🎉 Done."
