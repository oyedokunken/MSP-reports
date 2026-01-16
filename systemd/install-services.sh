#!/bin/bash
#
# MSP Reports - Systemd Service Installation Script
# 
# This script installs and enables the Laravel queue worker and scheduler services.
# Run with sudo: sudo ./install-services.sh
#

set -e

# Configuration - adjust these paths as needed
APP_PATH="/opt/mspreports/backend"
SERVICE_USER="www-data"
SERVICE_GROUP="www-data"
WORKER_COUNT=3  # Number of queue worker instances

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}MSP Reports - Systemd Service Installation${NC}"
echo "============================================"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (sudo)${NC}"
    exit 1
fi

# Verify application path exists
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}Error: Application path $APP_PATH does not exist${NC}"
    echo "Please update APP_PATH in this script or create the directory"
    exit 1
fi

# Verify artisan exists
if [ ! -f "$APP_PATH/artisan" ]; then
    echo -e "${RED}Error: Laravel artisan not found at $APP_PATH/artisan${NC}"
    exit 1
fi

# Create log files if they don't exist
echo -e "${YELLOW}Creating log files...${NC}"
touch "$APP_PATH/storage/logs/worker.log"
touch "$APP_PATH/storage/logs/scheduler.log"
chown "$SERVICE_USER:$SERVICE_GROUP" "$APP_PATH/storage/logs/worker.log"
chown "$SERVICE_USER:$SERVICE_GROUP" "$APP_PATH/storage/logs/scheduler.log"
chmod 664 "$APP_PATH/storage/logs/worker.log"
chmod 664 "$APP_PATH/storage/logs/scheduler.log"

# Stop existing services if running
echo -e "${YELLOW}Stopping any existing services...${NC}"
systemctl stop mspreports-scheduler.service 2>/dev/null || true
for i in $(seq 1 $WORKER_COUNT); do
    systemctl stop "mspreports-worker@$i.service" 2>/dev/null || true
done
systemctl stop mspreports-worker.service 2>/dev/null || true

# Copy service files to systemd directory
echo -e "${YELLOW}Installing service files...${NC}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp "$SCRIPT_DIR/mspreports-worker.service" /etc/systemd/system/
cp "$SCRIPT_DIR/mspreports-worker@.service" /etc/systemd/system/
cp "$SCRIPT_DIR/mspreports-scheduler.service" /etc/systemd/system/
cp "$SCRIPT_DIR/mspreports.target" /etc/systemd/system/

# Reload systemd daemon
echo -e "${YELLOW}Reloading systemd daemon...${NC}"
systemctl daemon-reload

# Enable services to start on boot
echo -e "${YELLOW}Enabling services...${NC}"
systemctl enable mspreports.target
systemctl enable mspreports-scheduler.service
for i in $(seq 1 $WORKER_COUNT); do
    systemctl enable "mspreports-worker@$i.service"
done

# Start services
echo -e "${YELLOW}Starting services...${NC}"
systemctl start mspreports-scheduler.service
for i in $(seq 1 $WORKER_COUNT); do
    systemctl start "mspreports-worker@$i.service"
done

# Wait a moment for services to start
sleep 2

# Check status
echo ""
echo -e "${GREEN}Service Status:${NC}"
echo "---------------"
systemctl status mspreports-scheduler.service --no-pager -l | head -15
echo ""
for i in $(seq 1 $WORKER_COUNT); do
    echo "Worker $i:"
    systemctl status "mspreports-worker@$i.service" --no-pager -l | head -10
    echo ""
done

echo -e "${GREEN}Installation complete!${NC}"
echo ""
echo "Useful commands:"
echo "  View worker status:    systemctl status mspreports-worker@1"
echo "  View scheduler status: systemctl status mspreports-scheduler"
echo "  View worker logs:      tail -f $APP_PATH/storage/logs/worker.log"
echo "  View scheduler logs:   tail -f $APP_PATH/storage/logs/scheduler.log"
echo "  Restart workers:       sudo systemctl restart 'mspreports-worker@*'"
echo "  Restart scheduler:     sudo systemctl restart mspreports-scheduler"
