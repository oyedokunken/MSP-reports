# MSP Reports - Native Deployment Guide

Complete guide for deploying MSP Reports on a native Linux server (non-Docker).

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Preparation](#server-preparation)
3. [Application Installation](#application-installation)
4. [Database Setup](#database-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [Service Configuration](#service-configuration)
7. [SSL/HTTPS Setup](#sslhttps-setup)
8. [Post-Installation](#post-installation)
9. [Verification Checklist](#verification-checklist)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Hardware Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 2 cores | 4+ cores |
| RAM | 4 GB | 8+ GB |
| Storage | 50 GB SSD | 100+ GB SSD |
| Network | 100 Mbps | 1 Gbps |

### Software Requirements

- **OS**: Ubuntu 22.04 LTS or 24.04 LTS (recommended)
- **PHP**: 8.3+
- **PostgreSQL**: 16+ with TimescaleDB extension
- **Redis**: 7+
- **Nginx**: Latest stable
- **Node.js**: 20+ (for building frontend)
- **Composer**: 2.x

---

## Server Preparation

### 1. Update System

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install PHP 8.3

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and required extensions
sudo apt install -y \
    php8.3-fpm \
    php8.3-cli \
    php8.3-common \
    php8.3-pgsql \
    php8.3-redis \
    php8.3-gd \
    php8.3-curl \
    php8.3-xml \
    php8.3-mbstring \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-ldap \
    php8.3-intl
```

### 3. Install PostgreSQL 16 with TimescaleDB

```bash
# Add PostgreSQL repository
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update

# Install PostgreSQL
sudo apt install -y postgresql-16 postgresql-contrib-16

# Add TimescaleDB repository
curl -s https://packagecloud.io/install/repositories/timescale/timescaledb/script.deb.sh | sudo bash

# Install TimescaleDB
sudo apt install -y timescaledb-2-postgresql-16

# Tune TimescaleDB
sudo timescaledb-tune --quiet --yes
sudo systemctl restart postgresql
```

### 4. Install Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 5. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

### 6. Install Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 7. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 8. Create Application User

```bash
# Create user for running the application
sudo useradd -m -s /bin/bash athena
sudo usermod -aG www-data athena
```

---

## Application Installation

### 1. Create Directory Structure

```bash
sudo mkdir -p /var/www/mspreports
sudo chown athena:www-data /var/www/mspreports
```

### 2. Clone Repository

```bash
cd /var/www/mspreports
sudo -u athena git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git .
```

### 3. Install Backend Dependencies

```bash
cd /var/www/mspreports/backend
sudo -u athena composer install --no-dev --optimize-autoloader
```

### 4. Configure Environment

```bash
sudo -u athena cp .env.example .env
sudo -u athena nano .env
```

**Key environment variables to configure:**

```env
APP_NAME="MSP Reports"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://reports.yourcompany.com.au

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mspreports
DB_USERNAME=mspreports
DB_PASSWORD=your_secure_password_here

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=reports@yourcompany.com.au
MAIL_FROM_NAME="MSP Reports"

# Gotenberg PDF Service (if using external)
GOTENBERG_URL=http://localhost:3000
```

### 5. Generate Application Key

```bash
sudo -u athena php artisan key:generate
```

### 6. Build Frontend

```bash
cd /var/www/mspreports/frontend
sudo -u athena npm ci
sudo -u athena npm run build

# Copy built files to backend public directory
sudo -u athena cp -r dist/* /var/www/mspreports/backend/public/
```

### 7. Set Permissions

```bash
cd /var/www/mspreports/backend

# Set ownership
sudo chown -R athena:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Make storage and cache writable
sudo chmod -R 775 storage bootstrap/cache
```

### 8. Create Storage Link

```bash
sudo -u athena php artisan storage:link
```

---

## Database Setup

### 1. Create Database and User

```bash
sudo -u postgres psql
```

```sql
-- Create database
CREATE DATABASE mspreports;

-- Create user
CREATE USER mspreports WITH ENCRYPTED PASSWORD 'your_secure_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE mspreports TO mspreports;

-- Connect to database
\c mspreports

-- Enable TimescaleDB extension
CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;

-- Grant schema permissions
GRANT ALL ON SCHEMA public TO mspreports;

\q
```

### 2. Run Migrations

```bash
cd /var/www/mspreports/backend
sudo -u athena php artisan migrate --force
```

### 3. Seed Initial Data (Optional)

```bash
sudo -u athena php artisan db:seed --force
```

---

## Web Server Configuration

### 1. Configure PHP-FPM

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
; Change user/group to match application
user = athena
group = www-data

; Increase workers for production
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

### 2. Configure Nginx

```bash
# Copy nginx config
sudo cp /var/www/mspreports/nginx/mspreports.conf /etc/nginx/sites-available/mspreports

# Edit to set your domain
sudo nano /etc/nginx/sites-available/mspreports
# Change: server_name reports.example.com -> your actual domain

# Enable site
sudo ln -s /etc/nginx/sites-available/mspreports /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

---

## Service Configuration

### 1. Install Systemd Services

```bash
cd /var/www/mspreports/systemd
sudo ./install-services.sh
```

### 2. Verify Services Running

```bash
# Check all services
systemctl status mspreports-scheduler
systemctl status mspreports-worker@1
systemctl status mspreports-worker@2
systemctl status mspreports-worker@3

# Or check via target
systemctl list-dependencies mspreports.target
```

---

## SSL/HTTPS Setup

### Option 1: Let's Encrypt (Recommended)

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d reports.yourcompany.com.au

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Option 2: Manual Certificate

1. Place certificate files:
   - `/etc/ssl/certs/mspreports.crt`
   - `/etc/ssl/private/mspreports.key`

2. Update nginx config to use HTTPS (uncomment HTTPS server block)

3. Reload nginx: `sudo systemctl reload nginx`

---

## Post-Installation

### 1. Create First Admin User

```bash
cd /var/www/mspreports/backend
sudo -u athena php artisan tinker
```

```php
// Create company
$company = App\Models\Company::create([
    'name' => 'Your Company Name',
    'status' => 'active',
]);

// Create admin user
$user = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@yourcompany.com.au',
    'password' => bcrypt('your_secure_password'),
    'company_id' => $company->company_id,
    'status' => 'active',
    'auth_method' => 'local',
]);

// Assign admin role
$adminRole = App\Models\Role::where('name', 'Admin')->first();
$user->role_id = $adminRole->role_id;
$user->save();

exit
```

### 2. Configure Caching

```bash
sudo -u athena php artisan config:cache
sudo -u athena php artisan route:cache
sudo -u athena php artisan view:cache
```

### 3. Set Up Log Rotation

Create `/etc/logrotate.d/mspreports`:

```
/var/www/mspreports/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 athena www-data
    sharedscripts
    postrotate
        systemctl reload php8.3-fpm > /dev/null 2>&1 || true
    endscript
}
```

### 4. Configure Firewall

```bash
# Allow HTTP and HTTPS
sudo ufw allow 'Nginx Full'

# Allow SSH (if not already)
sudo ufw allow ssh

# Enable firewall
sudo ufw enable
```

---

## Verification Checklist

Run through this checklist to verify the installation:

- [ ] **Web Access**: Can access `https://reports.yourcompany.com.au`
- [ ] **Login**: Can log in with admin credentials
- [ ] **API Health**: `curl https://reports.yourcompany.com.au/api/health` returns OK
- [ ] **Queue Workers**: `systemctl status mspreports-worker@1` shows active
- [ ] **Scheduler**: `systemctl status mspreports-scheduler` shows active
- [ ] **Redis**: `redis-cli ping` returns PONG
- [ ] **Database**: Can connect and query database
- [ ] **SSL**: Certificate is valid (check browser padlock)
- [ ] **Logs**: No errors in `/var/www/mspreports/backend/storage/logs/laravel.log`

---

## Troubleshooting

### 502 Bad Gateway

```bash
# Check PHP-FPM is running
sudo systemctl status php8.3-fpm

# Check socket exists
ls -la /var/run/php/php8.3-fpm.sock

# Check nginx error log
sudo tail -50 /var/log/nginx/mspreports-error.log
```

### Queue Jobs Not Processing

```bash
# Check worker status
systemctl status mspreports-worker@1

# Check worker logs
tail -100 /var/www/mspreports/backend/storage/logs/worker.log

# Check Redis connection
redis-cli ping

# Manually test queue
cd /var/www/mspreports/backend
sudo -u athena php artisan queue:work --once
```

### Database Connection Failed

```bash
# Test PostgreSQL connection
sudo -u postgres psql -d mspreports -c "SELECT 1"

# Check TimescaleDB extension
sudo -u postgres psql -d mspreports -c "SELECT extname FROM pg_extension"

# Verify .env database settings
grep DB_ /var/www/mspreports/backend/.env
```

### Permission Denied Errors

```bash
# Fix storage permissions
cd /var/www/mspreports/backend
sudo chown -R athena:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Clear caches
sudo -u athena php artisan cache:clear
sudo -u athena php artisan config:clear
```

### Slow Performance

```bash
# Enable opcache (check php.ini)
sudo nano /etc/php/8.3/fpm/php.ini
# Set: opcache.enable=1

# Cache Laravel config
sudo -u athena php artisan config:cache
sudo -u athena php artisan route:cache

# Check slow query log
sudo tail -100 /var/log/postgresql/postgresql-16-main.log
```

---

## Maintenance Commands

```bash
# Clear all caches
sudo -u athena php artisan optimize:clear

# Rebuild caches
sudo -u athena php artisan optimize

# Run pending migrations
sudo -u athena php artisan migrate --force

# Restart all services
sudo systemctl restart mspreports.target
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

# View application logs
tail -f /var/www/mspreports/backend/storage/logs/laravel.log

# View worker logs
tail -f /var/www/mspreports/backend/storage/logs/worker.log
```

---

## Backup Recommendations

1. **Database**: Daily pg_dump with retention
2. **Files**: Daily backup of `/var/www/mspreports/backend/storage`
3. **Config**: Version control `.env` separately (encrypted)

See `backend/scripts/` for backup scripts.

---

## Support

- **Documentation**: Check `/docs` folder in repository
- **Issues**: GitLab Issues
- **Email**: support@athenanetworks.com.au
