# Windows Server 2025 - Quick Reference

## First Time Setup

### 1. Prerequisites
```powershell
# Verify Docker Desktop is running
docker --version

# Verify Git is installed
git --version
```

### 2. Clone & Configure
```powershell
# Clone repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# Run setup script (assigns unique ports)
.\setup-dev-environment.ps1
# Enter your developer ID (1-20)

# Generate SSL certificates (using Git Bash)
bash docker/timescaledb/scripts/generate-ssl-certs.sh

# Create environment file
Copy-Item .env.example .env
```

### 3. Start Services
```powershell
# Start all Docker services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```

### 4. Access Your Services
Your URLs were displayed after running `setup-dev-environment.ps1`

**Example for Developer 1:**
- Frontend: http://localhost:5174
- API: http://localhost:8001/api
- MailHog: http://localhost:8026
- pgAdmin: http://localhost:5051

---

## Daily Commands

### Start/Stop Services
```powershell
# Start services
docker-compose up -d

# Stop services
docker-compose down

# Restart services
docker-compose restart
```

### View Logs
```powershell
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f timescaledb
```

### Run Artisan Commands
```powershell
# Run migrations
docker-compose exec app php artisan migrate

# Clear cache
docker-compose exec app php artisan cache:clear

# Access Tinker
docker-compose exec app php artisan tinker
```

### Access Containers
```powershell
# App container shell
docker-compose exec app bash

# Database shell
docker-compose exec timescaledb psql -U reporting_user -d reporting
```

---

## Common Tasks

### Update Code
```powershell
# Pull latest changes
git pull

# Restart services
docker-compose restart

# Run new migrations
docker-compose exec app php artisan migrate
```

### Clear Everything
```powershell
# Clear Laravel caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Restart services
docker-compose restart
```

### Fresh Database
```powershell
# ⚠️ Warning: Deletes all data!
docker-compose exec app php artisan migrate:fresh --seed
```

---

## Troubleshooting

### Port Conflicts
```powershell
# Check what's using a port
netstat -ano | findstr :8001

# Kill process by PID
taskkill /PID <PID> /F

# Or choose different developer ID
.\setup-dev-environment.ps1
```

### Docker Not Starting
```powershell
# Enable WSL 2
wsl --install
wsl --set-default-version 2

# Update WSL
wsl --update

# Restart Windows
```

### Permission Errors
```powershell
# Fix container permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www:www storage bootstrap/cache
```

### Services Not Responding
```powershell
# Stop everything
docker-compose down

# Remove containers
docker-compose rm -f

# Start fresh
docker-compose up -d
```

---

## PowerShell Aliases

Add to `$PROFILE`:

```powershell
# Docker shortcuts
function dcu { docker-compose up -d }
function dcd { docker-compose down }
function dcl { docker-compose logs -f $args }

# Reporting shortcuts
function rp-up { docker-compose up -d }
function rp-down { docker-compose down }
function rp-logs { docker-compose logs -f }
function rp-shell { docker-compose exec app bash }
function rp-artisan { docker-compose exec app php artisan $args }
function rp-migrate { docker-compose exec app php artisan migrate }
function rp-cache { 
    docker-compose exec app php artisan cache:clear
    docker-compose exec app php artisan config:clear
}

# Usage:
# rp-up
# rp-artisan migrate
# rp-logs
```

---

## Team Coordination

### Developer ID Roster

| Developer | ID | HTTP | Frontend | PostgreSQL |
|-----------|----|---------| ---------|------------|
| Josh | 1 | 8001 | 5174 | 5433 |
| Sarah | 2 | 8002 | 5175 | 5434 |
| Mike | 3 | 8003 | 5176 | 5435 |

### Best Practices
- Update roster before claiming ID
- Stop services when done: `docker-compose down`
- Clean up unused containers: `docker system prune`
- Communicate with team about resource usage

---

## Quick Checks

### Is Everything Running?
```powershell
docker ps
```

### Check Service Health
```powershell
# Test database connection
docker-compose exec app php artisan db:show

# Test SSL encryption
docker-compose exec timescaledb psql -U reporting_user -d reporting -c "SHOW ssl;"
```

### View Your Configuration
```powershell
# See your ports
Get-Content docker-compose.override.yml | Select-String "ports:"

# See your developer ID
Get-Content .env.local
```

---

## Getting Help

**Full Documentation:**
- Windows Setup: `docs/WINDOWS_SETUP.md`
- Multi-Developer: `docs/MULTI_DEVELOPER_SETUP.md`
- Docker Setup: `docs/DOCKER_SETUP.md`

**Contact:** josh@athenanetworks.com.au

---

## Emergency Commands

### Complete Reset
```powershell
# ⚠️ Warning: Deletes everything!

# Stop and remove containers
docker-compose down

# Remove volumes (deletes database)
docker volume rm reporting-timescaledb-data-dev1
docker volume rm reporting-redis-data-dev1

# Start fresh
.\setup-dev-environment.ps1
docker-compose up -d
```

### Check Disk Space
```powershell
# Docker disk usage
docker system df

# Clean up unused resources
docker system prune -a
```

---

**Keep this reference handy for quick access to common commands!**
