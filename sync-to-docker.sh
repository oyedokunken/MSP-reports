#!/bin/bash

# Sync files from /home/kristian/reporting to /root/reporting (Docker mount point)
# This is needed because Docker mounts /root/reporting but we edit in /home/kristian/reporting

echo "🔄 Syncing /home/kristian/reporting to /root/reporting..."

sudo rsync -av --delete \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '.git' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  /home/kristian/reporting/ /root/reporting/

echo "✅ Sync complete!"
echo ""
echo "🔧 Fixing permissions and creating directories..."
docker exec -u root reporting-app bash -c "
  # Create necessary directories
  mkdir -p /var/www/html/storage/app/reports
  mkdir -p /var/www/html/storage/logs
  mkdir -p /var/www/html/storage/framework/{cache,sessions,views}
  
  # Ensure log file exists
  touch /var/www/html/storage/logs/laravel.log
  
  # Fix all permissions (www is the PHP-FPM user in this container)
  chown -R www:www /var/www/html/storage
  chmod -R 775 /var/www/html/storage
  chown -R www:www /var/www/html/bootstrap/cache
  chmod -R 775 /var/www/html/bootstrap/cache
"
echo "✅ Permissions fixed!"
echo ""
echo "📋 Next steps:"
echo "   docker exec reporting-app php artisan config:clear"
echo "   docker exec reporting-app php artisan cache:clear"
echo "   docker exec -u root reporting-app supervisorctl restart all"
