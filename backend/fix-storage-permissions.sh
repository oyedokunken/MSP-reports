#!/bin/bash

# Fix storage permissions for Laravel application
# This script ensures proper permissions for storage directories

echo "Fixing storage permissions..."

# Create reports directory if it doesn't exist
mkdir -p storage/app/reports

# Set ownership to www-data (or www in Alpine)
chown -R www-data:www-data storage/ 2>/dev/null || chown -R www:www storage/ 2>/dev/null || chown -R nginx:nginx storage/ 2>/dev/null

# Set directory permissions to 775 (rwxrwxr-x)
find storage/ -type d -exec chmod 775 {} \;

# Set file permissions to 664 (rw-rw-r--)
find storage/ -type f -exec chmod 664 {} \;

# Ensure bootstrap/cache has proper permissions too
mkdir -p bootstrap/cache
chown -R www-data:www-data bootstrap/cache 2>/dev/null || chown -R www:www bootstrap/cache 2>/dev/null || chown -R nginx:nginx bootstrap/cache 2>/dev/null
chmod -R 775 bootstrap/cache

echo "Storage permissions fixed!"
echo ""
echo "Directory structure:"
ls -la storage/app/
