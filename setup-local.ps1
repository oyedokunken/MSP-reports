# Windows Setup Script (setup-local.ps1)
# Usage: .\setup-local.ps1
#
# PRE-REQUISITES - Install these BEFORE running this script:
#   1. PHP 8.3+ with extensions: ldap, pgsql, gd, curl, xml, mbstring, zip, bcmath, redis
#      Download: https://windows.php.net/download/ (use Thread Safe version)
#      Enable extensions in php.ini: extension=ldap, extension=pgsql, extension=pdo_pgsql, etc.
#   2. Composer - https://getcomposer.org/download/
#   3. Node.js LTS - https://nodejs.org/
#   4. PostgreSQL 16 - https://www.postgresql.org/download/windows/
#   5. TimescaleDB - https://docs.timescale.com/self-hosted/latest/install/installation-windows/
#   6. Redis (Memurai) - https://www.memurai.com/ OR run Redis in Docker
#   7. Docker Desktop (optional, for Gotenberg PDF) - https://www.docker.com/products/docker-desktop
#
# This script configures the application after pre-requisites are installed.

Write-Host "" 
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Local Development Setup (Windows)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Function to check command availability
function Test-CommandExists {
    param($name)
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
}

# ============================================
# STEP 1: Check Pre-requisites
# ============================================
Write-Host "=== Step 1: Checking Pre-requisites ===" -ForegroundColor Magenta
Write-Host ""

$missingDeps = @()

# PHP
if (Test-CommandExists "php") {
    $phpVersion = (php -v | Select-Object -First 1) -replace 'PHP (\d+\.\d+).*', '$1'
    Write-Host "[OK] PHP installed ($phpVersion)" -ForegroundColor Green
    
    # Check PHP version
    if ([version]$phpVersion -lt [version]"8.3") {
        Write-Host "[WARN] PHP 8.3+ required, you have $phpVersion" -ForegroundColor Yellow
        $missingDeps += "PHP 8.3+"
    }
} else {
    Write-Host "[MISSING] PHP" -ForegroundColor Red
    $missingDeps += "PHP 8.3+"
}

# Composer
if (Test-CommandExists "composer") {
    Write-Host "[OK] Composer installed" -ForegroundColor Green
} else {
    Write-Host "[MISSING] Composer" -ForegroundColor Red
    $missingDeps += "Composer"
}

# Node.js
if (Test-CommandExists "node") {
    $nodeVersion = (node -v)
    Write-Host "[OK] Node.js installed ($nodeVersion)" -ForegroundColor Green
} else {
    Write-Host "[MISSING] Node.js" -ForegroundColor Red
    $missingDeps += "Node.js"
}

# PostgreSQL
if (Test-CommandExists "psql") {
    Write-Host "[OK] PostgreSQL installed" -ForegroundColor Green
} else {
    Write-Host "[MISSING] PostgreSQL (psql not in PATH)" -ForegroundColor Red
    $missingDeps += "PostgreSQL 16"
}

# Redis/Memurai
$redisRunning = Get-Service -Name "Memurai" -ErrorAction SilentlyContinue
if ($redisRunning -or (Test-CommandExists "redis-cli")) {
    Write-Host "[OK] Redis/Memurai installed" -ForegroundColor Green
} else {
    Write-Host "[WARN] Redis/Memurai not detected (optional but recommended)" -ForegroundColor Yellow
}

# Docker (optional)
if (Test-CommandExists "docker") {
    Write-Host "[OK] Docker installed" -ForegroundColor Green
} else {
    Write-Host "[WARN] Docker not installed (needed for Gotenberg PDF generation)" -ForegroundColor Yellow
}

Write-Host ""

# Stop if critical dependencies missing
if ($missingDeps.Count -gt 0) {
    Write-Host "============================================" -ForegroundColor Red
    Write-Host " Missing required dependencies:" -ForegroundColor Red
    foreach ($dep in $missingDeps) {
        Write-Host "   - $dep" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host " Please install the missing dependencies and run this script again." -ForegroundColor Yellow
    Write-Host " See the comments at the top of this script for download links." -ForegroundColor Yellow
    Write-Host "============================================" -ForegroundColor Red
    exit 1
}

# ============================================
# STEP 2: Setup Backend
# ============================================
Write-Host ""
Write-Host "=== Step 2: Backend Setup ===" -ForegroundColor Magenta
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
Write-Host " .env configured for local development" -ForegroundColor Green

# Database Setup
Write-Host ""
Write-Host " Setting up Database..." -ForegroundColor Cyan

try {
    $env:PGPASSWORD = "postgres"
    $dbExists = psql -U postgres -h 127.0.0.1 -lqt 2>$null | Select-String "reporting"
    if ($dbExists) {
        Write-Host " Database 'reporting' already exists." -ForegroundColor Green
    } else {
        Write-Host "Creating database 'reporting'..."
        createdb -U postgres -h 127.0.0.1 reporting
        Write-Host " Database created" -ForegroundColor Green
    }
    
    # Create user if needed
    Write-Host "Creating database user 'reporting_user'..."
    psql -U postgres -h 127.0.0.1 -c "CREATE USER reporting_user WITH PASSWORD 'reporting_password';" 2>$null
    psql -U postgres -h 127.0.0.1 -c "GRANT ALL PRIVILEGES ON DATABASE reporting TO reporting_user;" 2>$null
    psql -U postgres -h 127.0.0.1 -d reporting -c "GRANT ALL ON SCHEMA public TO reporting_user;" 2>$null
    
    # Enable TimescaleDB
    Write-Host "Enabling TimescaleDB extension..."
    psql -U postgres -h 127.0.0.1 -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;" 2>$null
    Write-Host " TimescaleDB extension enabled" -ForegroundColor Green
} catch {
    Write-Host "  Database setup encountered issues. You may need to configure manually." -ForegroundColor Yellow
    Write-Host "   Ensure PostgreSQL is running and accessible." -ForegroundColor Yellow
}

# Install PHP Dependencies
Write-Host ""
Write-Host "Installing Composer dependencies..." -ForegroundColor Cyan
Write-Host "(If this fails due to missing extensions, enable them in your php.ini)" -ForegroundColor Gray
composer install --ignore-platform-req=ext-ldap

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
    Write-Host " Migrations complete" -ForegroundColor Green
}

Set-Location ..

# ============================================
# STEP 3: Setup Frontend
# ============================================
Write-Host ""
Write-Host "=== Step 3: Frontend Setup ===" -ForegroundColor Magenta
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
Write-Host " Setup Complete!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
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

