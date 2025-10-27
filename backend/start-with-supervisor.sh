#!/bin/bash

echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf

echo "Waiting for supervisor to start..."
sleep 2

echo "Starting queue workers and scheduler..."
/usr/bin/supervisorctl reread
/usr/bin/supervisorctl update
/usr/bin/supervisorctl start all

echo "Supervisor status:"
/usr/bin/supervisorctl status

echo ""
echo "Starting PHP-FPM..."
exec docker-php-entrypoint php-fpm
