#!/bin/bash

# setup-supervisor-host.sh
# Automates Supervisor setup on the host for Mark's environment.

echo "🚀 Setting up Supervisor on the host..."

# 1. Install Supervisor if missing
if ! command -v supervisord &> /dev/null; then
    echo "📦 Installing Supervisor..."
    sudo apt-get update && sudo apt-get install -y supervisor
else
    echo "✅ Supervisor already installed"
fi

# 2. Prepare Config
CONFIG_SOURCE="/home/mark/Music/reporting/backend/supervisor-host-workers.conf"
CONFIG_DEST="/etc/supervisor/conf.d/laravel-worker.conf"

if [ ! -f "$CONFIG_SOURCE" ]; then
    echo "❌ Source config file not found at $CONFIG_SOURCE"
    exit 1
fi

# 3. Copy Config (Requires sudo)
echo "📋 Copying configuration to /etc/supervisor/conf.d/..."
sudo cp "$CONFIG_SOURCE" "$CONFIG_DEST"

# 4. Ensure Log Directory Exists
echo "📁 ensuring log directory exists..."
mkdir -p /home/mark/Music/reporting/backend/storage/logs
chmod -R 775 /home/mark/Music/reporting/backend/storage/logs

# 5. Reload Supervisor
echo "♻️  Reloading Supervisor..."
sudo supervisorctl reread
sudo supervisorctl update

# 6. Start Processes
echo "▶️  Starting workers..."
sudo supervisorctl start all

# 7. Check Status
echo "📊 Current Status:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
sudo supervisorctl status
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "✨ Done! Your workers are now managed by Supervisor."
echo "Tip: Use 'sudo supervisorctl status' to check them anytime."
