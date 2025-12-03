#!/bin/bash

# Build Frontend and Deploy to Backend
# This script builds the React frontend and copies it to Laravel's public directory

set -e  # Exit on error

echo "=========================================="
echo "Building Frontend for Production"
echo "=========================================="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Change to frontend directory (we're already in it)
cd "$(dirname "$0")"

echo -e "${YELLOW}Step 1: Installing dependencies...${NC}"
npm install

echo -e "${YELLOW}Step 2: Building production bundle...${NC}"
npm run build

echo -e "${YELLOW}Step 3: Copying build to backend public directory...${NC}"
# Remove old frontend files from backend public directory
rm -rf ../backend/public/assets
rm -f ../backend/public/index.html

# Copy new build files
cp -r dist/* ../backend/public/

echo -e "${YELLOW}Step 4: Updating backend .htaccess for SPA routing...${NC}"
cat > ../backend/public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send API requests to Laravel
    RewriteCond %{REQUEST_URI} ^/api/
    RewriteRule ^ index.php [L]

    # Send all other requests to index.html for React Router
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.html [L]
</IfModule>
EOF

echo -e "${YELLOW}Step 5: Copying files to Docker container...${NC}"
docker cp ../backend/public/. reporting-app:/var/www/html/public/

echo -e "${YELLOW}Step 6: Setting permissions...${NC}"
docker exec -u root reporting-app chown -R www-data:www-data /var/www/html/public
docker exec -u root reporting-app chmod -R 755 /var/www/html/public

echo -e "${YELLOW}Step 7: Creating storage symlink...${NC}"
docker exec reporting-app php artisan storage:link || true

echo -e "${YELLOW}Step 8: Clearing application cache...${NC}"
docker exec reporting-app php artisan cache:clear
docker exec reporting-app php artisan config:clear
docker exec reporting-app php artisan view:clear

echo -e "${GREEN}=========================================="
echo -e "✅ Frontend built and deployed successfully!"
echo -e "==========================================${NC}"
echo ""
echo "Access your application at:"
echo "  - http://0.0.0.0:8081 (all interfaces)"
echo "  - http://192.168.9.85:8081 (local network)"
echo "  - http://localhost:8081 (local only)"
echo ""
echo "API endpoints available at /api/*"
