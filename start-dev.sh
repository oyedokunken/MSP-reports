#!/bin/bash

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
trap "kill $PID_QUEUE1 $PID_QUEUE2 $PID_QUEUE3 $PID_SCHED; exit" SIGINT

wait
