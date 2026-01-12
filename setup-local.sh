#!/bin/bash

# Exit on error
set -e

echo "🚀 Starting Local Setup..."

# Check for required commands
command -v php >/dev/null 2>&1 || { echo >&2 "❌ PHP is not installed. Please run: sudo apt install php8.3-cli php8.3-fpm php8.3-common php8.3-pgsql php8.3-gd php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip php8.3-bcmath php8.3-ldap php8.3-dev"; exit 1; }
command -v composer >/dev/null 2>&1 || { echo >&2 "❌ Composer is not installed. Please install it: https://getcomposer.org/download/"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo >&2 "❌ NPM is not installed. Please run: sudo apt install nodejs npm"; exit 1; }
command -v psql >/dev/null 2>&1 || { echo >&2 "❌ PostgreSQL client (psql) is not installed. Please run: sudo apt install postgresql-client"; exit 1; }
command -v redis-server >/dev/null 2>&1 || { echo >&2 "❌ Redis is not installed. Please run: sudo apt install redis-server"; exit 1; }
# Check for Nginx
if ! command -v nginx >/dev/null 2>&1; then
    echo "⚠️ Nginx not found. Installing..."
    sudo apt-get update
    sudo apt-get install -y nginx
fi

# Check for TimescaleDB
if ! dpkg -l | grep -q timescaledb-2-postgresql-16; then
    echo "⚠️ TimescaleDB not found. Installing..."
    
    # Add TimescaleDB Repository (PackageCloud) - Forcing Ubuntu Noble for Mint compatibility
    LIST_FILE="/etc/apt/sources.list.d/timescale_timescaledb.list"
    
    # Remove incorrect Mint repo if present
    if [ -f "$LIST_FILE" ] && grep -q "linuxmint" "$LIST_FILE"; then
        echo "⚠️  Found incorrect TimescaleDB repo (Linux Mint). Removing..."
        sudo rm "$LIST_FILE"
    fi

    # Add correct Ubuntu Noble repo if missing
    if [ ! -f "$LIST_FILE" ]; then
        echo "Adding TimescaleDB Repository (forcing Ubuntu Noble)..."
        curl -s https://packagecloud.io/install/repositories/timescale/timescaledb/script.deb.sh | sudo os=ubuntu dist=noble bash
    fi

    echo "Installing TimescaleDB package..."
    sudo apt-get install -y timescaledb-2-postgresql-16

    echo "Tuning TimescaleDB..."
    # Using --quiet --yes to avoid prompts
    sudo timescaledb-tune --quiet --yes
    
    echo "Restarting PostgreSQL..."
    sudo systemctl restart postgresql
fi

echo "✅ Dependencies found."

# 1. Setup Backend
echo "📦 Setting up Backend..."
cd backend

# Fix Permissions (Force ownership to current user)
echo "🔒 Fixing permissions..."
sudo chown -R $USER:www-data .
sudo find . -type f -exec chmod 664 {} \;
sudo find . -type d -exec chmod 775 {} \;
sudo chmod -R 777 storage bootstrap/cache

# Environment File
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Update .env for local usage
# Use sed to replace values. adapting for likely Linux sed.
# We force these updates even if .env exists to ensure we aren't using docker values
echo "Testing .env settings..."
# sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
# sed -i 's/REDIS_HOST=.*/REDIS_HOST=127.0.0.1/' .env
# sed -i 's/DB_USERNAME=.*/DB_USERNAME=postgres/' .env
# sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=password/' .env

echo "⚠️  Please ensure 'backend/.env' has the correct DB_USERNAME and DB_PASSWORD for your local PostgreSQL."

# Create Database if it doesn't exist
echo "🐘 Check/Create Database..."
if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw reporting; then
    echo "✅ Database 'reporting' already exists."
else
    echo "Creating database 'reporting'..."
    sudo -u postgres createdb reporting || echo "⚠️ Failed to create database. You may need to do it manually."
fi

# Enable TimescaleDB Extension
echo "⏱️  Enabling TimescaleDB extension..."
sudo -u postgres psql -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;" || echo "⚠️ Could not enable TimescaleDB. Ensure the package is installed."

# Install PHP dependencies
composer install

# Generate Key
php artisan key:generate

# Storage Link
php artisan storage:link

# Database Migration
# Ask user if they want to run migrations (might destroy data if pointing to existing db)
read -p "Run database migrations? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    php artisan migrate
fi

cd ..

# 2. Setup Frontend
echo "🎨 Setting up Frontend..."
cd frontend
npm install
npm run build
cd ..

echo "🎉 Setup Complete!"
echo "To start the backend queue workers: cd backend && php artisan queue:work"
echo "To start the scheduler: cd backend && php artisan schedule:work"
echo "Ensure Nginx is running with the provided configuration."
