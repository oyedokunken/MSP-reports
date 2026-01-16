#!/usr/bin/env bash
#
# MSP Reports - Deployment Script
#
# Usage: ./deploy.sh [options]
#
# This script pulls the latest code, runs migrations, and rebuilds the frontend.
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}==>${NC} $*"; }
warn()  { echo -e "${YELLOW}WARNING:${NC} $*"; }
error() { echo -e "${RED}ERROR:${NC} $*" >&2; }

SKIP_FRONTEND=false
SKIP_MIGRATE=false

for arg in "$@"; do
    case "$arg" in
        --skip-frontend) SKIP_FRONTEND=true ;;
        --skip-migrate) SKIP_MIGRATE=true ;;
        -h|--help)
            echo "Usage: ./deploy.sh [options]"
            echo ""
            echo "Options:"
            echo "  --skip-frontend   Skip frontend rebuild"
            echo "  --skip-migrate    Skip database migrations"
            echo "  -h, --help        Show this help"
            exit 0
            ;;
        *) error "Unknown option: $arg"; exit 1 ;;
    esac
done

cd "$ROOT_DIR"

info "Pulling latest changes..."
git fetch origin

# Check if we need to rebase
LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse @{u})
BASE=$(git merge-base @ @{u})

if [ "$LOCAL" = "$REMOTE" ]; then
    info "Already up to date"
elif [ "$LOCAL" = "$BASE" ]; then
    info "Pulling changes..."
    git pull --rebase
else
    warn "Local changes detected, attempting rebase..."
    git pull --rebase || {
        error "Rebase failed. Please resolve conflicts manually."
        exit 1
    }
fi

info "Clearing Laravel caches..."
cd "$BACKEND_DIR"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

info "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

if [ "$SKIP_MIGRATE" = false ]; then
    info "Running database migrations..."
    php artisan migrate --force
fi

info "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Restarting queue workers..."
sudo systemctl restart 'mspreports-worker@*' || warn "Failed to restart workers (may need sudo)"

if [ "$SKIP_FRONTEND" = false ]; then
    info "Installing frontend dependencies..."
    cd "$FRONTEND_DIR"
    npm ci

    info "Building frontend..."
    npm run build
fi

info "Deployment complete!"
echo ""
echo "Summary:"
echo "  - Code updated from origin"
echo "  - Laravel caches cleared and rebuilt"
[ "$SKIP_MIGRATE" = false ] && echo "  - Database migrations applied"
[ "$SKIP_FRONTEND" = false ] && echo "  - Frontend rebuilt"
echo "  - Queue workers restarted"
