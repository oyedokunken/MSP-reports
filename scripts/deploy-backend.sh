#!/bin/bash
set -e

# Configuration
REMOTE_USER="mark"
REMOTE_HOST="10.121.15.243"
REMOTE_DIR="~/reporting/backend/"
LOCAL_DIR="./backend/"

echo "🔧 Fixing remote permissions..."
ssh "$REMOTE_USER@$REMOTE_HOST" "echo 'password' | sudo -S chown -R mark:mark /home/mark/reporting/backend"

echo "🚀 Syncing backend files..."
rsync -avz --no-perms --no-owner --no-group \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='.git' \
    --exclude='storage' \
    --exclude='bootstrap/cache' \
    "$LOCAL_DIR" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"

echo "✅ Sync complete."

echo "🔄 Rebuilding/Restarting containers..."
# Ensure docker-compose points to Mark's directory
ssh "$REMOTE_USER@$REMOTE_HOST" "echo 'password' | sudo -S sed -i 's|/home/kristian/reporting/backend|/home/mark/reporting/backend|g' /root/reports-docker/docker-compose.yml"

# We need to restart to apply the volume path change from /home/kristian to /home/mark
ssh "$REMOTE_USER@$REMOTE_HOST" "sudo bash -c 'cd /root/reports-docker && docker compose up -d app nginx'"

echo "📦 Installing dependencies..."
ssh "$REMOTE_USER@$REMOTE_HOST" "echo 'password' | sudo -S chmod -R 777 /home/mark/reporting/backend && docker exec reporting-app composer install"

echo "🎉 Deployment done."
