#!/bin/bash

# Start Supervisor (which will start queue workers)
echo "Starting Supervisor..."
supervisord -c /etc/supervisor/supervisord.conf

# Wait a moment for supervisor to start
sleep 2

# Start PHP-FPM in foreground (keeps container running)
echo "Starting PHP-FPM..."
php-fpm
