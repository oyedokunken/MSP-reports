# Windows Setup Script (setup-local.ps1)
# Must run as Administrator if modifying system files or services is needed (mostly not for this)

Write-Host "🚀 Starting Local Setup (Windows)..." -ForegroundColor Cyan

# Function to check command availability
function Check-Command {
    param($name, $install)
    if (-not (Get-Command $name -ErrorAction SilentlyContinue)) {
        Write-Host "❌ $name is not installed. $install" -ForegroundColor Red
        exit 1
    }
}

# Check Dependencies
Check-Command "php" "Please install PHP (scoop install php or choco install php)"
Check-Command "composer" "Please install Composer (https://getcomposer.org)"
Check-Command "node" "Please install Node.js (scoop install nodejs or choco install nodejs)"
Check-Command "psql" "Please install PostgreSQL 16 (https://www.postgresql.org/download/windows/)"

Write-Host "✅ Core dependencies found." -ForegroundColor Green

# 1. Setup Backend
Write-Host "📦 Setting up Backend..." -ForegroundColor Cyan
Set-Location "backend"

# Environment File
if (-not (Test-Path ".env")) {
    Write-Host "Creating .env from .env.example..."
    Copy-Item ".env.example" ".env"
}

# Fix .env (optional manual step usually, but check)
Write-Host "⚠️  Please ensure 'backend/.env' has correct DB_USERNAME and DB_PASSWORD." -ForegroundColor Yellow

# Database Check
# Assuming "postgres" user and localhost:5432 default
Write-Host "🐘 Check/Create Database..." -ForegroundColor Cyan

try {
    # Try to list DBs. User needs PGPASSWORD set or pg_hba.conf trusted for local
    $dbExists = psql -U postgres -lqt | Select-String "reporting"
    if ($dbExists) {
        Write-Host "✅ Database 'reporting' already exists." -ForegroundColor Green
    } else {
        Write-Host "Creating database 'reporting'..."
        createdb -U postgres reporting
    }
} catch {
    Write-Host "⚠️  Could not check/create database automatically. Ensure Postgres is running and you have access." -ForegroundColor Yellow
}

# Enable TimescaleDB (Windows users usually have to install the .exe)
Write-Host "⏱️  Enabling TimescaleDB extension..."
try {
    psql -U postgres -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;"
} catch {
    Write-Host "⚠️  Could not enable TimescaleDB. Ensure it is installed (https://docs.timescale.com/self-hosted/latest/install/windows/)." -ForegroundColor Yellow
}

# Install PHP Dependencies
Write-Host "Installing Composer dependencies..."
composer install

# Key Generate
php artisan key:generate

# Storage Link
php artisan storage:link

# Migrations
$response = Read-Host "Run database migrations? (y/n)"
if ($response -eq 'y') {
    php artisan migrate
}

Set-Location ..

# 2. Setup Frontend
Write-Host "🎨 Setting up Frontend..." -ForegroundColor Cyan
Set-Location "frontend"
npm install
npm run build
Set-Location ..

Write-Host "🎉 Setup Complete!" -ForegroundColor Green
Write-Host "To run the app:"
Write-Host "1. Backend: cd backend; php artisan serve"
Write-Host "2. Frontend: cd frontend; npm run dev"
