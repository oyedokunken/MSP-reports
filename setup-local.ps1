# Windows Setup Script (setup-local.ps1)
# Run as Administrator for service installations
# Usage: .\setup-local.ps1

Write-Host "🚀 Starting Local Setup (Windows)..." -ForegroundColor Cyan
Write-Host "This script will install and configure all required services." -ForegroundColor Gray
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "⚠️  Not running as Administrator. Some installations may fail." -ForegroundColor Yellow
    Write-Host "   Recommend: Right-click PowerShell -> Run as Administrator" -ForegroundColor Yellow
    Write-Host ""
}

# Function to check command availability
function Test-CommandExists {
    param($name)
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
}

# Function to check if Chocolatey is installed
function Install-Chocolatey {
    if (-not (Test-CommandExists "choco")) {
        Write-Host "📦 Installing Chocolatey package manager..." -ForegroundColor Cyan
        Set-ExecutionPolicy Bypass -Scope Process -Force
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
        Invoke-Expression ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
        refreshenv
    }
}

# ============================================
# STEP 1: Install Package Manager (Chocolatey)
# ============================================
Write-Host "" 
Write-Host "=== Step 1: Package Manager ===" -ForegroundColor Magenta
Install-Chocolatey

# ============================================
# STEP 2: Install Core Dependencies
# ============================================
Write-Host ""
Write-Host "=== Step 2: Core Dependencies ===" -ForegroundColor Magenta

# PHP
if (-not (Test-CommandExists "php")) {
    Write-Host "📦 Installing PHP 8.3..." -ForegroundColor Cyan
    choco install php --version=8.3 -y
    refreshenv
} else {
    Write-Host "✅ PHP is installed" -ForegroundColor Green
}

# Composer
if (-not (Test-CommandExists "composer")) {
    Write-Host "📦 Installing Composer..." -ForegroundColor Cyan
    choco install composer -y
    refreshenv
} else {
    Write-Host "✅ Composer is installed" -ForegroundColor Green
}

# Node.js
if (-not (Test-CommandExists "node")) {
    Write-Host "📦 Installing Node.js LTS..." -ForegroundColor Cyan
    choco install nodejs-lts -y
    refreshenv
} else {
    Write-Host "✅ Node.js is installed" -ForegroundColor Green
}

# ============================================
# STEP 3: Install Database (PostgreSQL + TimescaleDB)
# ============================================
Write-Host ""
Write-Host "=== Step 3: Database (PostgreSQL + TimescaleDB) ===" -ForegroundColor Magenta

if (-not (Test-CommandExists "psql")) {
    Write-Host "📦 Installing PostgreSQL 16..." -ForegroundColor Cyan
    choco install postgresql16 --params '/Password:postgres' -y
    refreshenv
    
    # Add to PATH if not already
    $pgPath = "C:\Program Files\PostgreSQL\16\bin"
    if (Test-Path $pgPath) {
        $env:Path += ";$pgPath"
    }
} else {
    Write-Host "✅ PostgreSQL is installed" -ForegroundColor Green
}

# TimescaleDB
Write-Host "📦 TimescaleDB installation..." -ForegroundColor Cyan
Write-Host "   ⚠️  TimescaleDB must be installed manually on Windows:" -ForegroundColor Yellow
Write-Host "   1. Download from: https://docs.timescale.com/self-hosted/latest/install/installation-windows/" -ForegroundColor Yellow
Write-Host "   2. Run the installer and select your PostgreSQL 16 installation" -ForegroundColor Yellow
Write-Host "   3. Restart PostgreSQL service after installation" -ForegroundColor Yellow
Write-Host ""

# ============================================
# STEP 4: Install Redis
# ============================================
Write-Host "=== Step 4: Redis ===" -ForegroundColor Magenta

# Check if Redis is running
$redisRunning = Get-Process redis-server -ErrorAction SilentlyContinue
if (-not $redisRunning) {
    Write-Host "📦 Installing Redis..." -ForegroundColor Cyan
    
    # Option 1: Use Memurai (Redis-compatible for Windows)
    if (-not (Test-CommandExists "memurai")) {
        Write-Host "   Installing Memurai (Redis-compatible server for Windows)..." -ForegroundColor Gray
        choco install memurai-developer -y
    }
    
    # Start Memurai service
    Start-Service Memurai -ErrorAction SilentlyContinue
    Write-Host "✅ Redis (Memurai) installed and started" -ForegroundColor Green
} else {
    Write-Host "✅ Redis is running" -ForegroundColor Green
}

# ============================================
# STEP 5: Install Gotenberg (PDF Generation)
# ============================================
Write-Host ""
Write-Host "=== Step 5: Gotenberg (PDF Generation) ===" -ForegroundColor Magenta

if (Test-CommandExists "docker") {
    Write-Host "📦 Starting Gotenberg via Docker..." -ForegroundColor Cyan
    docker pull gotenberg/gotenberg:8
    docker run -d --name gotenberg -p 3000:3000 gotenberg/gotenberg:8 2>$null
    if ($?) {
        Write-Host "✅ Gotenberg running on http://localhost:3000" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Gotenberg container may already exist. Try: docker start gotenberg" -ForegroundColor Yellow
    }
} else {
    Write-Host "⚠️  Docker not found. Gotenberg requires Docker:" -ForegroundColor Yellow
    Write-Host "   Install Docker Desktop: https://www.docker.com/products/docker-desktop" -ForegroundColor Yellow
    Write-Host "   Then run: docker run -d --name gotenberg -p 3000:3000 gotenberg/gotenberg:8" -ForegroundColor Yellow
}

# ============================================
# STEP 6: Setup Backend
# ============================================
Write-Host ""
Write-Host "=== Step 6: Backend Setup ===" -ForegroundColor Magenta
Set-Location "backend"

# Environment File
if (-not (Test-Path ".env")) {
    Write-Host "Creating .env from .env.example..." -ForegroundColor Cyan
    Copy-Item ".env.example" ".env"
}

# Update .env for local development
Write-Host "Configuring .env for local development..." -ForegroundColor Cyan
$envContent = Get-Content ".env" -Raw

# Replace Docker hostnames with localhost
$envContent = $envContent -replace 'DB_HOST=reporting-timescaledb', 'DB_HOST=127.0.0.1'
$envContent = $envContent -replace 'DB_HOST=timescaledb', 'DB_HOST=127.0.0.1'
$envContent = $envContent -replace 'REDIS_HOST=redis', 'REDIS_HOST=127.0.0.1'
$envContent = $envContent -replace 'GOTENBERG_URL=http://gotenberg:3000', 'GOTENBERG_URL=http://127.0.0.1:3000'
$envContent = $envContent -replace 'DB_SSLMODE=require', 'DB_SSLMODE=prefer'

Set-Content ".env" $envContent
Write-Host "✅ .env configured for local development" -ForegroundColor Green

# Database Setup
Write-Host ""
Write-Host "🐘 Setting up Database..." -ForegroundColor Cyan

try {
    $env:PGPASSWORD = "postgres"
    $dbExists = psql -U postgres -h 127.0.0.1 -lqt 2>$null | Select-String "reporting"
    if ($dbExists) {
        Write-Host "✅ Database 'reporting' already exists." -ForegroundColor Green
    } else {
        Write-Host "Creating database 'reporting'..."
        createdb -U postgres -h 127.0.0.1 reporting
        Write-Host "✅ Database created" -ForegroundColor Green
    }
    
    # Create user if needed
    Write-Host "Creating database user 'reporting_user'..."
    psql -U postgres -h 127.0.0.1 -c "CREATE USER reporting_user WITH PASSWORD 'reporting_password';" 2>$null
    psql -U postgres -h 127.0.0.1 -c "GRANT ALL PRIVILEGES ON DATABASE reporting TO reporting_user;" 2>$null
    psql -U postgres -h 127.0.0.1 -d reporting -c "GRANT ALL ON SCHEMA public TO reporting_user;" 2>$null
    
    # Enable TimescaleDB
    Write-Host "Enabling TimescaleDB extension..."
    psql -U postgres -h 127.0.0.1 -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;" 2>$null
    Write-Host "✅ TimescaleDB extension enabled" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Database setup encountered issues. You may need to configure manually." -ForegroundColor Yellow
    Write-Host "   Ensure PostgreSQL is running and accessible." -ForegroundColor Yellow
}

# Install PHP Dependencies
Write-Host ""
Write-Host "Installing Composer dependencies..." -ForegroundColor Cyan
composer install

# Generate App Key
Write-Host "Generating application key..." -ForegroundColor Cyan
php artisan key:generate

# Storage Link
Write-Host "Creating storage link..." -ForegroundColor Cyan
php artisan storage:link 2>$null

# Migrations
Write-Host ""
$response = Read-Host "Run database migrations? (y/n)"
if ($response -eq 'y') {
    Write-Host "Running migrations..." -ForegroundColor Cyan
    php artisan migrate
    Write-Host "✅ Migrations complete" -ForegroundColor Green
}

Set-Location ..

# ============================================
# STEP 7: Setup Frontend
# ============================================
Write-Host ""
Write-Host "=== Step 7: Frontend Setup ===" -ForegroundColor Magenta
Set-Location "frontend"

Write-Host "Installing npm dependencies..." -ForegroundColor Cyan
npm install

Write-Host "Building frontend..." -ForegroundColor Cyan
npm run build

Set-Location ..

# ============================================
# COMPLETE
# ============================================
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "🎉 Setup Complete!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Services installed:" -ForegroundColor Cyan
Write-Host "  ✅ PHP 8.3"
Write-Host "  ✅ Composer"
Write-Host "  ✅ Node.js"
Write-Host "  ✅ PostgreSQL 16"
Write-Host "  ✅ Redis (Memurai)"
Write-Host "  ⚠️  TimescaleDB (manual install required)"
Write-Host "  ⚠️  Gotenberg (requires Docker)"
Write-Host ""
Write-Host "To start development:" -ForegroundColor Cyan
Write-Host "  1. Start workers:    .\start-dev.ps1"
Write-Host "  2. Backend API:      cd backend; php artisan serve --host=0.0.0.0 --port=8000"
Write-Host "  3. Frontend:         cd frontend; npm run dev"
Write-Host ""
Write-Host "Default URLs:" -ForegroundColor Cyan
Write-Host "  Backend API:  http://localhost:8000"
Write-Host "  Frontend:     http://localhost:5173"
Write-Host "  Gotenberg:    http://localhost:3000"
