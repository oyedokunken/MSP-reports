#!/bin/bash

echo "🚀 Deploying Supervisor to Docker Container..."
echo ""

# Configuration
CONTAINER_NAME="reporting-app"
CONFIG_FILE="supervisor-laravel-worker.conf"
CONTAINER_PATH="/var/www/html"

# Step 1: Copy supervisor config to container
echo "📋 Step 1: Copying supervisor config to container..."
docker cp $CONFIG_FILE $CONTAINER_NAME:/etc/supervisor/conf.d/
if [ $? -eq 0 ]; then
    echo "✅ Config copied successfully"
else
    echo "❌ Failed to copy config"
    exit 1
fi

# Step 2: Update paths in config (from host path to container path)
echo ""
echo "🔧 Step 2: Updating paths in config..."
docker exec $CONTAINER_NAME sh -c "sed -i 's|/home/kristian/reporting/backend|$CONTAINER_PATH|g' /etc/supervisor/conf.d/$CONFIG_FILE"
echo "✅ Paths updated"

# Step 3: Update user (container runs as www-data)
echo ""
echo "👤 Step 3: Updating user to www-data..."
docker exec $CONTAINER_NAME sh -c "sed -i 's|user=kristian|user=www-data|g' /etc/supervisor/conf.d/$CONFIG_FILE"
echo "✅ User updated"

# Step 4: Create log directory
echo ""
echo "📁 Step 4: Creating log directory..."
docker exec $CONTAINER_NAME mkdir -p $CONTAINER_PATH/storage/logs
docker exec $CONTAINER_NAME chown -R www-data:www-data $CONTAINER_PATH/storage/logs
echo "✅ Log directory created"

# Step 5: Check if supervisord is running
echo ""
echo "🔍 Step 5: Checking if supervisord is running..."
docker exec $CONTAINER_NAME pgrep supervisord > /dev/null
if [ $? -eq 0 ]; then
    echo "⚠️  Supervisord already running, reloading config..."
    docker exec $CONTAINER_NAME supervisorctl reread
    docker exec $CONTAINER_NAME supervisorctl update
else
    echo "🚀 Starting supervisord..."
    docker exec -d $CONTAINER_NAME supervisord -c /etc/supervisor/supervisord.conf
    sleep 2
    docker exec $CONTAINER_NAME supervisorctl reread
    docker exec $CONTAINER_NAME supervisorctl update
fi

# Step 6: Start all processes
echo ""
echo "▶️  Step 6: Starting all processes..."
docker exec $CONTAINER_NAME supervisorctl start all
sleep 2

# Step 7: Check status
echo ""
echo "📊 Step 7: Checking status..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
docker exec $CONTAINER_NAME supervisorctl status
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Step 8: Verify processes are running
echo ""
echo "🔍 Verifying processes..."
RUNNING_COUNT=$(docker exec $CONTAINER_NAME supervisorctl status | grep RUNNING | wc -l)
if [ $RUNNING_COUNT -eq 3 ]; then
    echo "✅ SUCCESS! All 3 processes are running:"
    echo "   - 2 Queue Workers"
    echo "   - 1 Scheduler"
else
    echo "⚠️  Warning: Expected 3 processes, found $RUNNING_COUNT running"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Deployment Complete!"
echo ""
echo "📝 Useful Commands:"
echo "   Check status:  docker exec $CONTAINER_NAME supervisorctl status"
echo "   View logs:     docker exec $CONTAINER_NAME supervisorctl tail -f laravel-worker"
echo "   Restart:       docker exec $CONTAINER_NAME supervisorctl restart all"
echo "   Stop:          docker exec $CONTAINER_NAME supervisorctl stop all"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
