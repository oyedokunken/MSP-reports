#!/bin/bash

# This script ensures supervisor is running in the reporting-app container
# Run this after Docker starts or server reboots

CONTAINER_NAME="reporting-app"

echo "Checking if $CONTAINER_NAME is running..."
if ! docker ps | grep -q $CONTAINER_NAME; then
    echo "❌ Container $CONTAINER_NAME is not running!"
    exit 1
fi

echo "✅ Container is running"
echo ""

echo "Checking if supervisor is running..."
if docker exec -u root $CONTAINER_NAME pgrep -x supervisord > /dev/null 2>&1; then
    echo "✅ Supervisor is already running"
    docker exec -u root $CONTAINER_NAME supervisorctl status
else
    echo "⚠️  Supervisor is not running, starting it..."
    
    # Copy config if needed
    if [ -f "supervisor-laravel-worker.conf" ]; then
        docker cp supervisor-laravel-worker.conf $CONTAINER_NAME:/etc/supervisor/conf.d/
        docker exec -u root $CONTAINER_NAME sed -i 's|user=kristian|user=www-data|g' /etc/supervisor/conf.d/supervisor-laravel-worker.conf
        docker exec -u root $CONTAINER_NAME sed -i 's|/home/kristian/reporting/backend|/var/www/html|g' /etc/supervisor/conf.d/supervisor-laravel-worker.conf
    fi
    
    # Start supervisor
    docker exec -u root -d $CONTAINER_NAME supervisord -c /etc/supervisor/supervisord.conf
    sleep 3
    
    # Load config and start processes
    docker exec -u root $CONTAINER_NAME supervisorctl reread
    docker exec -u root $CONTAINER_NAME supervisorctl update
    docker exec -u root $CONTAINER_NAME supervisorctl start all
    
    echo ""
    echo "✅ Supervisor started!"
    docker exec -u root $CONTAINER_NAME supervisorctl status
fi
