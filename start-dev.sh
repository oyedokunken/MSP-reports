#!/bin/bash
#
# MSP Reports - Development Server Starter (Linux/macOS)
#
# Starts queue workers and scheduler for local development.
# Run setup-local.sh first if you haven't already.
#
# Usage: ./start-dev.sh
#

# Function to check service status
check_service() {
    if systemctl is-active --quiet "$1"; then
        echo "✅ $1 is running"
    else
        echo "❌ $1 is NOT running. Please start it: sudo systemctl start $1"
    fi
}

echo "🔍 Checking System Services..."
check_service postgresql
check_service redis-server

echo "--------------------------------"
echo "🚀 Starting Laravel Workers..."
echo "Press Ctrl+C to stop"

# Run queue worker and scheduler in parallel (simple dev mode)
# In a real daemon setup updates Supervisord, but this is for "running it"
cd backend

echo "Starting Worker 1..."
php artisan queue:work --queue=high,default,low &
PID_QUEUE1=$!

echo "Starting Worker 2..."
php artisan queue:work --queue=high,default,low &
PID_QUEUE2=$!

echo "Starting Worker 3..."
php artisan queue:work --queue=high,default,low &
PID_QUEUE3=$!

php artisan schedule:work &
PID_SCHED=$!

# Trap Ctrl+C to kill background processes
echo ""
echo "Workers and Scheduler running!"
echo "Press Ctrl+C to stop all processes"
echo ""
echo "In separate terminals, run:"
echo "  Backend:   cd backend && php artisan serve --port=8000"
echo "  Frontend:  cd frontend && npm run dev"
echo ""

# Trap Ctrl+C to kill background processes
trap "echo ''; echo 'Stopping workers...'; kill $PID_QUEUE1 $PID_QUEUE2 $PID_QUEUE3 $PID_SCHED 2>/dev/null; echo 'Done.'; exit" SIGINT SIGTERM

wait
