# Windows Server 2025 Setup Guide

## Prerequisites

### 1. Install Docker Desktop for Windows

**Download and Install:**
1. Download Docker Desktop from https://www.docker.com/products/docker-desktop
2. Run the installer
3. Enable WSL 2 backend (recommended)
4. Restart Windows Server

**Verify Installation:**
```powershell
docker --version
docker-compose --version
```

### 2. Install Git for Windows

**Download and Install:**
1. Download from https://git-scm.com/download/win
2. Run installer with default options
3. Choose "Git Bash" and "Git from the command line"

**Verify Installation:**
```powershell
git --version
```

### 3. Configure Docker Resources

**Docker Desktop Settings:**
1. Right-click Docker Desktop icon in system tray
2. Click "Settings"
3. Go to "Resources" → "Advanced"
4. Set:
   - CPUs: 4 (minimum)
   - Memory: 8 GB (minimum)
   - Swap: 2 GB
   - Disk image size: 60 GB
5. Click "Apply & Restart"

---

## Multi-Developer Setup (RDS Server)

### Step 1: Clone Repository

Open **PowerShell** as Administrator:

```powershell
# Navigate to your workspace
cd C:\Users\YourUsername\Projects

# Clone repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting
```

### Step 2: Configure Developer Environment

Run the PowerShell setup script:

```powershell
# Run setup script
.\setup-dev-environment.ps1

# Enter your developer ID when prompted (1-20)
```

**Developer ID Assignment:**
- Coordinate with your team to get a unique ID
- Each developer needs a different ID to avoid port conflicts

### Step 3: Generate SSL Certificates

```powershell
# Using Git Bash (recommended)
bash docker/timescaledb/scripts/generate-ssl-certs.sh

# OR using PowerShell (if OpenSSL installed)
# See "Installing OpenSSL" section below
```

### Step 4: Create Environment File

```powershell
# Copy example environment file
Copy-Item .env.example .env
```

### Step 5: Start Docker Services

```powershell
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f
```

### Step 6: Install Application Dependencies

```powershell
# Install PHP dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run database migrations
docker-compose exec app php artisan migrate

# Verify encryption (optional)
docker-compose exec timescaledb psql -U reporting_user -d reporting -c "SHOW ssl;"
```

### Step 7: Access Your Services

Your URLs will be displayed after running `setup-dev-environment.ps1`.

**Example for Developer 1:**
- Frontend: http://localhost:5174
- API: http://localhost:8001/api
- MailHog: http://localhost:8026
- pgAdmin: http://localhost:5051
- Redis Commander: http://localhost:8082

Open these URLs in your browser (Chrome, Edge, Firefox).

---

## Installing OpenSSL on Windows

### Option 1: Using Git Bash (Recommended)

Git for Windows includes OpenSSL. Use Git Bash to run the certificate generation script:

```bash
# Open Git Bash
bash docker/timescaledb/scripts/generate-ssl-certs.sh
```

### Option 2: Install OpenSSL Separately

**Download:**
1. Download from https://slproweb.com/products/Win32OpenSSL.html
2. Install "Win64 OpenSSL v3.x.x Light"
3. Add to PATH: `C:\Program Files\OpenSSL-Win64\bin`

**Verify:**
```powershell
openssl version
```

---

## Windows-Specific Commands

### PowerShell Equivalents

| Linux/Mac | Windows PowerShell |
|-----------|-------------------|
| `cp .env.example .env` | `Copy-Item .env.example .env` |
| `rm -rf vendor` | `Remove-Item -Recurse -Force vendor` |
| `chmod +x script.sh` | Not needed (use `.\script.ps1`) |
| `ls -la` | `Get-ChildItem` or `dir` |
| `cat file.txt` | `Get-Content file.txt` |

### Docker Commands

All Docker commands work the same:

```powershell
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f app

# Access container shell
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php artisan migrate
```

---

## Common Issues & Solutions

### Issue 1: Docker Desktop Not Starting

**Symptoms:**
- "Docker Desktop is starting..." never completes
- "WSL 2 installation is incomplete"

**Solutions:**

**Enable WSL 2:**
```powershell
# Run as Administrator
wsl --install
wsl --set-default-version 2

# Restart Windows
```

**Update WSL:**
```powershell
wsl --update
```

**Check Hyper-V:**
1. Open "Turn Windows features on or off"
2. Enable "Hyper-V" and "Windows Subsystem for Linux"
3. Restart Windows

### Issue 2: Port Already in Use

**Symptoms:**
```
Error: bind: address already in use
```

**Solutions:**

**Check what's using the port:**
```powershell
# Check port 8001 (example)
netstat -ano | findstr :8001
```

**Kill the process:**
```powershell
# Get PID from netstat output
taskkill /PID <PID> /F
```

**Or choose a different developer ID:**
```powershell
.\setup-dev-environment.ps1
# Enter a different ID
```

### Issue 3: Permission Denied Errors

**Symptoms:**
```
Permission denied: '/var/www/html/storage'
```

**Solution:**
```powershell
# Fix permissions inside container
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www:www storage bootstrap/cache
```

### Issue 4: Line Ending Issues (CRLF vs LF)

**Symptoms:**
```
bash: ./script.sh: /bin/bash^M: bad interpreter
```

**Solution:**

**Configure Git to use LF:**
```powershell
git config --global core.autocrlf false
git config --global core.eol lf

# Re-clone repository
cd ..
Remove-Item -Recurse -Force reporting
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
```

### Issue 5: Docker Volume Mounting Issues

**Symptoms:**
- Files not syncing between Windows and container
- Changes not reflected

**Solutions:**

**Check Docker Desktop file sharing:**
1. Docker Desktop → Settings → Resources → File Sharing
2. Add `C:\Users\YourUsername\Projects` to shared paths
3. Apply & Restart

**Use WSL 2 backend:**
1. Docker Desktop → Settings → General
2. Enable "Use the WSL 2 based engine"
3. Apply & Restart

### Issue 6: Slow Performance

**Symptoms:**
- Docker containers very slow
- High CPU usage

**Solutions:**

**Use WSL 2 (not Hyper-V):**
- WSL 2 is much faster than Hyper-V backend
- Docker Desktop → Settings → General → "Use WSL 2 based engine"

**Clone inside WSL 2:**
```powershell
# Open WSL 2
wsl

# Clone in WSL filesystem (faster)
cd ~
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting
```

**Increase Docker resources:**
- Docker Desktop → Settings → Resources
- Increase CPUs and Memory

---

## Firewall Configuration

### Allow Docker Ports

If Windows Firewall blocks Docker:

```powershell
# Run as Administrator

# Allow Docker Desktop
New-NetFirewallRule -DisplayName "Docker Desktop" -Direction Inbound -Program "C:\Program Files\Docker\Docker\Docker Desktop.exe" -Action Allow

# Allow your developer ports (example for Developer 1)
New-NetFirewallRule -DisplayName "Reporting Dev1 HTTP" -Direction Inbound -LocalPort 8001 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Reporting Dev1 Frontend" -Direction Inbound -LocalPort 5174 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Reporting Dev1 PostgreSQL" -Direction Inbound -LocalPort 5433 -Protocol TCP -Action Allow
```

---

## Remote Desktop Considerations

### Accessing Services from Your Local Machine

If you're connecting to Windows Server 2025 via RDP:

**Option 1: Access via RDP Session**
- Open browser inside RDP session
- Use `http://localhost:8001` (your developer ports)

**Option 2: Port Forwarding (Advanced)**
- Configure RDP to forward local ports to remote ports
- Requires RDP client configuration

**Option 3: Network Access**
- If server has accessible IP/hostname
- Use `http://server-ip:8001`
- Requires firewall rules (see above)

---

## Team Coordination on RDS

### Developer ID Roster

Maintain a shared document (Teams, SharePoint, etc.):

| Developer | ID | Ports | Status |
|-----------|----|---------| -------|
| Josh | 1 | 8001, 5174, 5433 | Active |
| Sarah | 2 | 8002, 5175, 5434 | Active |
| Mike | 3 | 8003, 5176, 5435 | Active |

### Best Practices

1. **Claim Your ID:**
   - Update roster before running setup
   - Don't use someone else's ID

2. **Stop Services When Done:**
   ```powershell
   docker-compose down
   ```
   - Frees up resources for other developers
   - Prevents port conflicts

3. **Clean Up Regularly:**
   ```powershell
   # Remove unused containers
   docker system prune -a

   # Remove unused volumes (careful!)
   docker volume prune
   ```

4. **Communicate:**
   - Let team know if you're using the server
   - Coordinate resource-intensive operations

---

## Useful PowerShell Aliases

Add to your PowerShell profile (`$PROFILE`):

```powershell
# Docker shortcuts
function dcu { docker-compose up -d }
function dcd { docker-compose down }
function dcl { docker-compose logs -f $args }
function dce { docker-compose exec $args }

# Reporting shortcuts
function reporting-up { docker-compose up -d }
function reporting-down { docker-compose down }
function reporting-logs { docker-compose logs -f }
function reporting-shell { docker-compose exec app bash }
function reporting-artisan { docker-compose exec app php artisan $args }

# Usage examples:
# reporting-up
# reporting-artisan migrate
# reporting-logs
```

---

## Performance Tips

### 1. Use WSL 2 Backend
- Much faster than Hyper-V
- Better file system performance

### 2. Clone Inside WSL 2
```bash
# In WSL 2
cd ~
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting
./setup-dev-environment.sh
docker-compose up -d
```

### 3. Exclude from Windows Defender
```powershell
# Run as Administrator
Add-MpPreference -ExclusionPath "C:\Users\YourUsername\Projects\reporting"
Add-MpPreference -ExclusionPath "C:\ProgramData\Docker"
```

### 4. Use SSD Storage
- Store project on SSD, not HDD
- Docker Desktop should be on SSD

---

## Troubleshooting Checklist

- [ ] Docker Desktop running?
- [ ] WSL 2 enabled and updated?
- [ ] Ran `setup-dev-environment.ps1`?
- [ ] Unique developer ID (not conflicting)?
- [ ] `.env` file created?
- [ ] SSL certificates generated?
- [ ] Firewall allowing Docker ports?
- [ ] Sufficient disk space (20GB+)?
- [ ] Sufficient RAM (8GB+ allocated to Docker)?

---

## Getting Help

### Check Logs

```powershell
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f timescaledb

# Last 100 lines
docker-compose logs --tail=100 app
```

### Check Service Status

```powershell
# List running containers
docker ps

# Check specific container
docker inspect reporting-app-dev1
```

### Access Container Shell

```powershell
# App container
docker-compose exec app bash

# Database container
docker-compose exec timescaledb bash
```

### Verify Database Connection

```powershell
# From app container
docker-compose exec app php artisan db:show

# Direct PostgreSQL connection
docker-compose exec timescaledb psql -U reporting_user -d reporting
```

---

## Additional Resources

- **Docker Desktop Docs**: https://docs.docker.com/desktop/windows/
- **WSL 2 Docs**: https://docs.microsoft.com/en-us/windows/wsl/
- **Git for Windows**: https://git-scm.com/download/win
- **PowerShell Docs**: https://docs.microsoft.com/en-us/powershell/

---

## Summary

✅ **Windows Server 2025 Compatible**
✅ **PowerShell Setup Script** (`setup-dev-environment.ps1`)
✅ **Multi-Developer Support** (up to 20 developers)
✅ **Complete Isolation** (ports, containers, volumes)
✅ **Comprehensive Troubleshooting**
✅ **Performance Optimized** (WSL 2 backend)

**Questions?** Contact josh@athenanetworks.com.au
