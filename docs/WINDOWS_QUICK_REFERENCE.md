# Windows Server 2025 - Quick Reference

> **💡 New!** All commands now use the `dev.ps1` PowerShell script for a consistent, cross-platform experience. It automatically handles SSL certificates, detects your developer ID, and shows your correct URLs.

## First Time Setup

### 1. Prerequisites
```powershell
# Verify Docker Desktop is running
docker --version

# Verify Git is installed (includes Git Bash for SSL certificate generation)
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
```

### 3. Install Everything
```powershell
# One command to install everything
.\dev.ps1 install

# This will:
# - Generate SSL certificates (if needed)
# - Start Docker services
# - Install Composer dependencies
# - Generate app key
# - Run migrations
# - Install frontend dependencies
```

### 4. Access Your Services
Your URLs are displayed after installation completes.

**Example for Developer 1:**
- Frontend: http://localhost:5174
- API: http://localhost:8001/api
- MailHog: http://localhost:8026
- pgAdmin: http://localhost:5051
- Redis Commander: http://localhost:8082
- MCP Server: http://localhost:3001

**Check your URLs anytime:**
```powershell
.\dev.ps1 status
```

---

## Daily Commands

**All commands use the `dev.ps1` script for consistency:**

### Start/Stop Services
```powershell
# Start services (auto-generates SSL certs if needed)
.\dev.ps1 up

# Stop services
.\dev.ps1 down

# Restart services
.\dev.ps1 restart

# Rebuild containers
.\dev.ps1 build
```

### View Logs
```powershell
# All services
.\dev.ps1 logs

# Specific service
.\dev.ps1 logs-app
.\dev.ps1 logs-queue
.\dev.ps1 logs-scheduler
.\dev.ps1 mcp-logs
```

### Run Artisan Commands
```powershell
# Run migrations
.\dev.ps1 migrate

# Clear cache
.\dev.ps1 cache-clear

# Access Tinker
.\dev.ps1 tinker

# Any artisan command
.\dev.ps1 artisan route:list
.\dev.ps1 artisan make:model Example
```

### Access Containers
```powershell
# App container shell
.\dev.ps1 shell

# Database shell
.\dev.ps1 db-shell

# Redis CLI
.\dev.ps1 redis-cli
```

### Check Status
```powershell
# Show all services and your URLs
.\dev.ps1 status
```

---

## Common Tasks

### Update Code
```powershell
# Pull latest changes
git pull

# Restart services
.\dev.ps1 restart

# Run new migrations
.\dev.ps1 migrate
```

### Clear Everything
```powershell
# Clear all Laravel caches
.\dev.ps1 cache-clear

# Or optimize for production
.\dev.ps1 optimize
```

### Fresh Database
```powershell
# ⚠️ Warning: Deletes all data!
.\dev.ps1 fresh

# Or just migrations without seed
.\dev.ps1 migrate-fresh
```

### Database Operations
```powershell
# Backup database
.\dev.ps1 db-backup

# Restore from backup.sql
.\dev.ps1 db-restore

# Verify SSL encryption
.\dev.ps1 db-verify-ssl
```

### Frontend Development
```powershell
# Install frontend dependencies
.\dev.ps1 frontend-install

# Build for production
.\dev.ps1 frontend-build

# Start dev server
.\dev.ps1 frontend-dev
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

### SSL Certificate Issues
```powershell
# Regenerate SSL certificates
.\dev.ps1 generate-certs

# Verify encryption is working
.\dev.ps1 verify-encryption
```

### Permission Errors
```powershell
# Fix container permissions (use dev.ps1 shell first)
.\dev.ps1 shell
# Then inside container:
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
exit
```

### Services Not Responding
```powershell
# Stop everything
.\dev.ps1 down

# Start fresh
.\dev.ps1 up

# Or rebuild if needed
.\dev.ps1 build
```

---

## PowerShell Aliases (Optional)

Add to `$PROFILE` for even shorter commands:

```powershell
# Navigate to project
function rp { Set-Location "C:\path\to\reporting" }

# Common dev.ps1 shortcuts
function rp-up { .\dev.ps1 up }
function rp-down { .\dev.ps1 down }
function rp-logs { .\dev.ps1 logs }
function rp-status { .\dev.ps1 status }
function rp-shell { .\dev.ps1 shell }
function rp-migrate { .\dev.ps1 migrate }
function rp-artisan { .\dev.ps1 artisan $args }

# Usage:
# rp              # Navigate to project
# rp-up           # Start services
# rp-status       # Check status
# rp-artisan migrate
```

**Or just use the dev.ps1 script directly - it's already very simple!**

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
- Stop services when done: `.\dev.ps1 down`
- Clean up unused containers: `docker system prune`
- Communicate with team about resource usage
- Use `.\dev.ps1 status` to check your assigned ports

---

## Quick Checks

### Is Everything Running?
```powershell
# Best way - shows status and URLs
.\dev.ps1 status

# Or check Docker directly
docker ps
```

### Check Service Health
```powershell
# Test database connection
.\dev.ps1 artisan db:show

# Test SSL encryption
.\dev.ps1 verify-encryption

# Or specific SSL check
.\dev.ps1 db-verify-ssl
```

### View Your Configuration
```powershell
# See all your URLs and ports
.\dev.ps1 status

# See your developer ID
Get-Content .env.local

# See your port mappings
Get-Content docker-compose.override.yml | Select-String "ports:"
```

### Available Commands
```powershell
# See all available commands
.\dev.ps1 help
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

# Use the clean command (prompts for confirmation)
.\dev.ps1 clean

# Or manually:
# Stop and remove containers
.\dev.ps1 down

# Remove volumes (deletes database) - replace dev1 with your ID
docker volume rm reporting-timescaledb-data-dev1
docker volume rm reporting-redis-data-dev1

# Start fresh
.\setup-dev-environment.ps1
.\dev.ps1 install
```

### Check Disk Space
```powershell
# Docker disk usage
docker system df

# Clean up unused resources
docker system prune -a

# Clean Laravel cache files
.\dev.ps1 clean-cache
```

---

## Quick Command Reference

| Task | Command |
|------|---------|
| **Setup** | `.\setup-dev-environment.ps1` |
| **Install** | `.\dev.ps1 install` |
| **Start** | `.\dev.ps1 up` |
| **Stop** | `.\dev.ps1 down` |
| **Status** | `.\dev.ps1 status` |
| **Logs** | `.\dev.ps1 logs` |
| **Shell** | `.\dev.ps1 shell` |
| **Migrate** | `.\dev.ps1 migrate` |
| **Artisan** | `.\dev.ps1 artisan <command>` |
| **Help** | `.\dev.ps1 help` |

**Keep this reference handy for quick access to common commands!**
