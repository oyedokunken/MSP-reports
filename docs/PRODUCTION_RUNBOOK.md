# MSP Reports: Production Runbook for TTS

> **Quick-reference deployment guide for Josh.**
> For full details, see [DEPLOYMENT_NATIVE.md](DEPLOYMENT_NATIVE.md).

**Date:** 13 February 2026
**Version:** 1.0.0
**Target:** Territory Technology Solutions (TTS) on-premise server

---

## Pre-Flight Checklist

Ensure these are installed on the production server before starting:

| Component | Version | Check Command |
|-----------|---------|---------------|
| PHP | 8.3+ | `php -v` |
| Composer | 2.x | `composer -V` |
| PostgreSQL | 16+ | `psql --version` |
| TimescaleDB | 2.x | `psql -c "SELECT extversion FROM pg_extension WHERE extname='timescaledb'"` |
| Redis | 7+ | `redis-cli --version` |
| Nginx | Latest | `nginx -v` |
| Node.js | 20+ | `node -v` |
| Gotenberg | 8.x | `curl http://localhost:3000/health` |

---

## Step 1: Pull Latest Code

```bash
cd /var/www/mspreports
sudo -u athena git pull origin main
```

## Step 2: Install Dependencies

```bash
# Backend
cd /var/www/mspreports/backend
sudo -u athena composer install --no-dev --optimize-autoloader

# Frontend
cd /var/www/mspreports/frontend
sudo -u athena npm ci
sudo -u athena npm run build

# Copy built frontend to backend public
sudo -u athena cp -r dist/* /var/www/mspreports/backend/public/
```

## Step 3: Configure Environment

```bash
cd /var/www/mspreports/backend
sudo -u athena nano .env
```

**Critical production settings:**

```env
# === MUST CHANGE ===
APP_NAME="MSP Reports"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Australia/Darwin
APP_URL=https://reports.tts.com.au          # Your actual domain
FRONTEND_URL=https://reports.tts.com.au     # Same as APP_URL in production

# === DATABASE ===
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mspreports
DB_USERNAME=mspreports
DB_PASSWORD=<STRONG_PASSWORD_HERE>

# === REDIS (caching, queues, rate limiting) ===
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

# === SESSION SECURITY ===
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=reports.tts.com.au           # Your domain
SESSION_LIFETIME=120

# === CORS (frontend URL only) ===
CORS_ALLOWED_ORIGINS=https://reports.tts.com.au

# === LOGGING ===
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

# === EMAIL (for report delivery) ===
MAIL_MAILER=smtp
MAIL_HOST=<SMTP_HOST>
MAIL_PORT=587
MAIL_USERNAME=<SMTP_USER>
MAIL_PASSWORD=<SMTP_PASSWORD>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=reports@tts.com.au
MAIL_FROM_NAME="TTS Reports"

# === BROADCASTING (real-time notifications) ===
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=mspreports
REVERB_APP_KEY=<GENERATE_RANDOM_32_CHAR>
REVERB_APP_SECRET=<GENERATE_RANDOM_32_CHAR>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="reports.tts.com.au"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"

# === PDF GENERATION ===
GOTENBERG_URL=http://localhost:3000
GOTENBERG_TIMEOUT=30
GOTENBERG_ENABLED=true

# === EMBEDDING SERVICE (AI customer matching) ===
EMBEDDING_SERVICE_URL=http://127.0.0.1:5050
EMBEDDING_SERVICE_ENABLED=true

# === SECURITY ===
BCRYPT_ROUNDS=12
```

Generate the app key:

```bash
sudo -u athena php artisan key:generate
```

## Step 4: Database Setup

If the database doesn't exist yet:

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE mspreports;
CREATE USER mspreports WITH ENCRYPTED PASSWORD '<STRONG_PASSWORD>';
GRANT ALL PRIVILEGES ON DATABASE mspreports TO mspreports;
\c mspreports
CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;
GRANT ALL ON SCHEMA public TO mspreports;
\q
```

## Step 5: Run Migrations

```bash
cd /var/www/mspreports/backend
sudo -u athena php artisan migrate --force
```

**Note:** There are 6 pending migrations. The `add_unique_index_to_integration_data_external_id` migration takes ~34 seconds, this is normal.

## Step 6: Seed Initial Data

```bash
sudo -u athena php artisan db:seed --force
```

## Step 7: Configure Nginx

```bash
# Copy config
sudo cp /var/www/mspreports/nginx/mspreports.conf /etc/nginx/sites-available/mspreports

# Edit domain name
sudo nano /etc/nginx/sites-available/mspreports
# Change: server_name reports.example.com → reports.tts.com.au

# Enable site
sudo ln -sf /etc/nginx/sites-available/mspreports /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and reload
sudo nginx -t
sudo systemctl reload nginx
```

## Step 8: SSL Certificate

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d reports.tts.com.au
sudo certbot renew --dry-run
```

## Step 9: Install Systemd Services

```bash
cd /var/www/mspreports/systemd
sudo ./install-services.sh
```

This installs:
- **mspreports-scheduler**: Runs Laravel scheduler every minute
- **mspreports-worker@1,2,3**: Queue workers for background jobs (syncs, reports, emails)
- **mspreports-embedding**: Python embedding service for AI customer matching

## Step 10: Start Gotenberg (PDF Service)

```bash
# If using Docker for Gotenberg only:
docker run -d --name gotenberg --restart unless-stopped -p 3000:3000 gotenberg/gotenberg:8
```

## Step 11: Cache & Optimize

```bash
cd /var/www/mspreports/backend
sudo -u athena php artisan config:cache
sudo -u athena php artisan route:cache
sudo -u athena php artisan view:cache
sudo -u athena php artisan storage:link
```

## Step 12: Set Permissions

```bash
cd /var/www/mspreports/backend
sudo chown -R athena:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

## Step 13: Firewall

```bash
sudo ufw allow 'Nginx Full'
sudo ufw allow ssh
sudo ufw enable
```

---

## Post-Deployment Verification

Run through each of these:

```bash
# 1. Redis
redis-cli ping
# Expected: PONG

# 2. Database
cd /var/www/mspreports/backend
sudo -u athena php artisan tinker --execute="echo DB::select('SELECT 1')[0]->{'?column?'};"
# Expected: 1

# 3. Queue workers
systemctl status mspreports-worker@1
# Expected: active (running)

# 4. Scheduler
systemctl status mspreports-scheduler
# Expected: active (running)

# 5. Gotenberg
curl -s http://localhost:3000/health | head -1
# Expected: {"status":"up"}

# 6. Web access
curl -sI https://reports.tts.com.au | head -5
# Expected: HTTP/2 200

# 7. API health
curl -s https://reports.tts.com.au/api/monitoring/status
# Expected: JSON with status info

# 8. SSL
curl -sI https://reports.tts.com.au | grep -i strict
# Expected: Strict-Transport-Security header present

# 9. Check logs for errors
tail -20 /var/www/mspreports/backend/storage/logs/laravel.log
```

---

## First Login & Setup

1. **Login** at `https://reports.tts.com.au` with seeded admin credentials
2. **Change password immediately** (Settings → Profile)
3. **Upload company logo** (Settings → Branding)
4. **Set company details** (name, email, phone, address)
5. **Add integrations** one by one:
   - Autotask → enter API credentials → test → save
   - Datto RMM → enter API credentials → test → save
   - CIPP → enter API credentials → test → save
   - (repeat for each integration TTS uses)
6. **Wait for first sync** (automatic, or click Manual Sync)
7. **Map customers** (Platform → Customers → Map Customers)
8. **Set Source of Truth** (usually Autotask)
9. **Configure report templates** (Reports → Templates)
10. **Set up report schedules** (Reports → Schedules)
11. **Test report generation** (generate one manually, verify PDF)
12. **Test email delivery** (send a test report via email)

---

## Ongoing Deployment (Updates)

For future updates, use the deploy script:

```bash
cd /var/www/mspreports
sudo -u athena ./deploy.sh
```

This automatically:
- Pulls latest code from git
- Clears and rebuilds Laravel caches
- Runs database migrations
- Rebuilds frontend
- Restarts queue workers

Options:
- `./deploy.sh --skip-frontend`: Skip frontend rebuild
- `./deploy.sh --skip-migrate`: Skip migrations

---

## Common Maintenance Commands

```bash
# View live logs
tail -f /var/www/mspreports/backend/storage/logs/laravel.log

# Restart all services
sudo systemctl restart mspreports.target
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

# Clear all caches
cd /var/www/mspreports/backend
sudo -u athena php artisan optimize:clear

# Check queue status
sudo -u athena php artisan queue:monitor

# Run a manual sync for an integration
sudo -u athena php artisan tinker --execute="dispatch(new App\Jobs\SyncIntegrationJob(App\Models\Integration::find(1)));"

# Generate a report manually
sudo -u athena php artisan report:generate --schedule=1

# Check migration status
sudo -u athena php artisan migrate:status
```

---

## Backup Strategy

```bash
# Database backup (add to cron for daily)
pg_dump -U mspreports -h 127.0.0.1 mspreports | gzip > /backups/mspreports_$(date +%Y%m%d).sql.gz

# File backup (storage directory)
tar czf /backups/mspreports_files_$(date +%Y%m%d).tar.gz /var/www/mspreports/backend/storage/app

# Recommended cron (add via: sudo crontab -e)
0 2 * * * pg_dump -U mspreports -h 127.0.0.1 mspreports | gzip > /backups/mspreports_$(date +\%Y\%m\%d).sql.gz
0 3 * * * tar czf /backups/mspreports_files_$(date +\%Y\%m\%d).tar.gz /var/www/mspreports/backend/storage/app
0 4 * * * find /backups -name "*.gz" -mtime +30 -delete
```

---

## Emergency Contacts

- **Platform Issues**: Mark / Kristian
- **Infrastructure**: Josh
- **Email**: support@athenanetworks.com.au
