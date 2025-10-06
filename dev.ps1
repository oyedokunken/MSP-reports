# Multi-Tenant Reporting Platform - Development Commands (PowerShell)
# Windows equivalent of Makefile

param(
    [Parameter(Position=0)]
    [string]$Command = "help",
    
    [Parameter(Position=1)]
    [string]$Arg1 = "",
    
    [Parameter(ValueFromRemainingArguments=$true)]
    [string[]]$RemainingArgs
)

# Detect docker-compose command (prefer docker-compose if available, otherwise use docker compose)
$DOCKER_COMPOSE = Get-Command docker-compose -ErrorAction SilentlyContinue
if ($DOCKER_COMPOSE) {
    $DC = "docker-compose"
} else {
    $DC = "docker compose"
}

# Helper function to get developer ID from .env.local
function Get-DeveloperID {
    if (Test-Path ".env.local") {
        $envContent = Get-Content ".env.local" -Raw
        if ($envContent -match 'DEVELOPER_ID=(\d+)') {
            return [int]$matches[1]
        }
    }
    return 0  # Default/not configured
}

# Helper function to calculate ports based on developer ID
function Get-DeveloperPorts {
    param([int]$DevID)
    
    return @{
        NGINX = 8000 + $DevID
        POSTGRES = 5432 + $DevID
        REDIS = 6379 + $DevID
        FRONTEND = 5173 + $DevID
        MAILHOG_SMTP = 1025 + $DevID
        MAILHOG_UI = 8025 + $DevID
        PGADMIN = 5050 + $DevID
        REDIS_CMD = 8081 + $DevID
        MCP = 3000 + $DevID
    }
}

# Helper function to check for SSL certificates
function Test-SSLCertificates {
    $certPath = "./docker/timescaledb/ssl/server.crt"
    $keyPath = "./docker/timescaledb/ssl/server.key"
    
    if (-not (Test-Path $certPath) -or -not (Test-Path $keyPath)) {
        Write-Host "⚠️  SSL certificates not found!" -ForegroundColor Yellow
        Write-Host "   Generating certificates now..." -ForegroundColor Cyan
        Write-Host ""
        Generate-Certificates
        return $false
    }
    return $true
}

function Show-Help {
    Write-Host "Multi-Tenant Reporting Platform - Development Commands" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Docker Management:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 up              - Start all services"
    Write-Host "  .\dev.ps1 down            - Stop all services"
    Write-Host "  .\dev.ps1 restart         - Restart all services"
    Write-Host "  .\dev.ps1 build           - Rebuild containers"
    Write-Host "  .\dev.ps1 logs            - View all logs"
    Write-Host "  .\dev.ps1 logs-app        - View app logs"
    Write-Host "  .\dev.ps1 logs-queue      - View queue worker logs"
    Write-Host "  .\dev.ps1 logs-scheduler  - View scheduler logs"
    Write-Host ""
    Write-Host "Application:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 install         - Initial setup (composer, npm, key, migrate)"
    Write-Host "  .\dev.ps1 shell           - Access app container shell"
    Write-Host "  .\dev.ps1 artisan <cmd>   - Run artisan command"
    Write-Host "  .\dev.ps1 migrate         - Run database migrations"
    Write-Host "  .\dev.ps1 seed            - Seed database"
    Write-Host "  .\dev.ps1 fresh           - Fresh database with seed"
    Write-Host "  .\dev.ps1 test            - Run tests"
    Write-Host "  .\dev.ps1 cache-clear     - Clear all caches"
    Write-Host ""
    Write-Host "Database:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 db-shell        - Access PostgreSQL shell"
    Write-Host "  .\dev.ps1 db-backup       - Backup database"
    Write-Host "  .\dev.ps1 db-restore      - Restore database from backup.sql"
    Write-Host "  .\dev.ps1 db-verify-ssl   - Verify SSL encryption is enabled"
    Write-Host ""
    Write-Host "Security:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 generate-certs  - Generate SSL certificates for PostgreSQL"
    Write-Host "  .\dev.ps1 verify-encryption - Verify database encryption settings"
    Write-Host ""
    Write-Host "Redis:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 redis-cli       - Access Redis CLI"
    Write-Host "  .\dev.ps1 redis-flush     - Flush Redis cache"
    Write-Host ""
    Write-Host "Frontend:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 frontend-install - Install frontend dependencies"
    Write-Host "  .\dev.ps1 frontend-build   - Build frontend for production"
    Write-Host "  .\dev.ps1 frontend-dev     - Start frontend dev server"
    Write-Host ""
    Write-Host "Cleanup:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 clean           - Stop and remove all containers/volumes"
    Write-Host "  .\dev.ps1 clean-cache     - Remove all cache files"
    Write-Host ""
    Write-Host "Development Helpers:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 tinker          - Laravel Tinker REPL"
    Write-Host "  .\dev.ps1 routes          - List all routes"
    Write-Host "  .\dev.ps1 models          - Show model information"
    Write-Host "  .\dev.ps1 status          - Show service status and URLs"
    Write-Host ""
    Write-Host "Queue & Scheduler:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 queue-restart   - Restart queue worker"
    Write-Host "  .\dev.ps1 queue-work      - Run queue worker"
    Write-Host "  .\dev.ps1 scheduler-run   - Run scheduler"
    Write-Host ""
    Write-Host "MCP Server:" -ForegroundColor Yellow
    Write-Host "  .\dev.ps1 mcp-logs        - View MCP server logs"
    Write-Host "  .\dev.ps1 mcp-restart     - Restart MCP server"
}

# Docker Management
function Start-Services {
    # Check for SSL certificates before starting
    Test-SSLCertificates | Out-Null
    & $DC up -d
}

function Stop-Services {
    & $DC down
}

function Restart-Services {
    & $DC restart
}

function Build-Services {
    # Check for SSL certificates before building
    Test-SSLCertificates | Out-Null
    & $DC up -d --build
}

function Show-Logs {
    & $DC logs -f
}

function Show-AppLogs {
    & $DC logs -f app
}

function Show-QueueLogs {
    & $DC logs -f queue
}

function Show-SchedulerLogs {
    & $DC logs -f scheduler
}

function Show-NginxLogs {
    & $DC logs -f nginx
}

# Initial Setup
function Install-Application {
    # Check if developer environment is configured
    if (-not (Test-Path ".env.local")) {
        Write-Host "⚠️  Developer environment not configured!" -ForegroundColor Yellow
        Write-Host "   Please run: .\setup-dev-environment.ps1" -ForegroundColor Cyan
        Write-Host ""
        return
    }
    
    Write-Host "Starting services..." -ForegroundColor Cyan
    Start-Services
    
    Write-Host "Installing Composer dependencies..." -ForegroundColor Cyan
    & $DC exec app composer install
    
    Write-Host "Copying .env file..." -ForegroundColor Cyan
    if (-not (Test-Path .env)) {
        Copy-Item .env.example .env
    }
    
    Write-Host "Generating application key..." -ForegroundColor Cyan
    & $DC exec app php artisan key:generate
    
    Write-Host "Running migrations..." -ForegroundColor Cyan
    & $DC exec app php artisan migrate
    
    Write-Host "Installing frontend dependencies..." -ForegroundColor Cyan
    Set-Location frontend
    npm install
    Set-Location ..
    
    Write-Host ""
    Write-Host "✅ Installation complete!" -ForegroundColor Green
    
    # Show URLs based on developer ID
    $devID = Get-DeveloperID
    $ports = Get-DeveloperPorts -DevID $devID
    
    Write-Host "Frontend: http://localhost:$($ports.FRONTEND)" -ForegroundColor Yellow
    Write-Host "API: http://localhost:$($ports.NGINX)/api" -ForegroundColor Yellow
    Write-Host "MailHog: http://localhost:$($ports.MAILHOG_UI)" -ForegroundColor Yellow
    Write-Host "pgAdmin: http://localhost:$($ports.PGADMIN)" -ForegroundColor Yellow
}

# Application Commands
function Open-Shell {
    & $DC exec app bash
}

function Run-Artisan {
    $artisanCmd = $Arg1
    if ($RemainingArgs) {
        $artisanCmd += " " + ($RemainingArgs -join " ")
    }
    & $DC exec app php artisan $artisanCmd
}

function Run-Migrate {
    & $DC exec app php artisan migrate
}

function Run-MigrateFresh {
    & $DC exec app php artisan migrate:fresh
}

function Run-Seed {
    & $DC exec app php artisan db:seed
}

function Run-Fresh {
    & $DC exec app php artisan migrate:fresh --seed
}

function Run-Tests {
    & $DC exec app php artisan test
}

function Clear-Cache {
    & $DC exec app php artisan cache:clear
    & $DC exec app php artisan config:clear
    & $DC exec app php artisan route:clear
    & $DC exec app php artisan view:clear
}

function Optimize-Application {
    & $DC exec app php artisan config:cache
    & $DC exec app php artisan route:cache
    & $DC exec app php artisan view:cache
}

# Database Commands
function Open-DatabaseShell {
    & $DC exec timescaledb psql -U reporting_user -d reporting
}

function Backup-Database {
    $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $backupFile = "backup-$timestamp.sql"
    Write-Host "Creating database backup..." -ForegroundColor Cyan
    & $DC exec timescaledb pg_dump -U reporting_user reporting > $backupFile
    Write-Host "✅ Backup created: $backupFile" -ForegroundColor Green
}

function Restore-Database {
    Write-Host "Restoring database from backup.sql..." -ForegroundColor Cyan
    Get-Content backup.sql | & $DC exec -T timescaledb psql -U reporting_user reporting
    Write-Host "✅ Database restored" -ForegroundColor Green
}

# Redis Commands
function Open-RedisCli {
    & $DC exec redis redis-cli
}

function Flush-Redis {
    & $DC exec redis redis-cli FLUSHALL
}

# Frontend Commands
function Install-Frontend {
    Set-Location frontend
    npm install
    Set-Location ..
}

function Build-Frontend {
    Set-Location frontend
    npm run build
    Set-Location ..
}

function Start-FrontendDev {
    Set-Location frontend
    npm run dev
    Set-Location ..
}

# Queue & Scheduler
function Restart-Queue {
    & $DC restart queue
}

function Run-QueueWork {
    & $DC exec app php artisan queue:work
}

function Run-Scheduler {
    & $DC exec app php artisan schedule:run
}

# Cleanup
function Clean-All {
    $confirmation = Read-Host "⚠️  This will remove all containers and volumes. Are you sure? [y/N]"
    if ($confirmation -eq 'y' -or $confirmation -eq 'Y') {
        & $DC down -v
        Write-Host "✅ Cleanup complete" -ForegroundColor Green
    } else {
        Write-Host "Cancelled" -ForegroundColor Yellow
    }
}

function Clean-CacheFiles {
    Remove-Item -Path storage/framework/cache/* -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -Path storage/framework/sessions/* -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -Path storage/framework/views/* -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -Path bootstrap/cache/*.php -Force -ErrorAction SilentlyContinue
}

# Development Helpers
function Open-Tinker {
    & $DC exec app php artisan tinker
}

function Show-Routes {
    & $DC exec app php artisan route:list
}

function Show-Models {
    & $DC exec app php artisan model:show
}

function Show-Status {
    Write-Host "=== Docker Services Status ===" -ForegroundColor Cyan
    & $DC ps
    Write-Host ""
    
    # Get developer ID and calculate ports
    $devID = Get-DeveloperID
    
    if ($devID -eq 0) {
        Write-Host "⚠️  Developer environment not configured" -ForegroundColor Yellow
        Write-Host "   Run: .\setup-dev-environment.ps1" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "=== Default Application URLs ===" -ForegroundColor Cyan
        Write-Host "Frontend:        http://localhost:5173" -ForegroundColor Yellow
        Write-Host "API:             http://localhost:8000/api" -ForegroundColor Yellow
        Write-Host "MailHog:         http://localhost:8025" -ForegroundColor Yellow
        Write-Host "pgAdmin:         http://localhost:5050" -ForegroundColor Yellow
        Write-Host "Redis Commander: http://localhost:8081" -ForegroundColor Yellow
    } else {
        $ports = Get-DeveloperPorts -DevID $devID
        
        Write-Host "=== Developer $devID - Application URLs ===" -ForegroundColor Cyan
        Write-Host "Frontend:        http://localhost:$($ports.FRONTEND)" -ForegroundColor Yellow
        Write-Host "API:             http://localhost:$($ports.NGINX)/api" -ForegroundColor Yellow
        Write-Host "MailHog:         http://localhost:$($ports.MAILHOG_UI)" -ForegroundColor Yellow
        Write-Host "pgAdmin:         http://localhost:$($ports.PGADMIN)" -ForegroundColor Yellow
        Write-Host "Redis Commander: http://localhost:$($ports.REDIS_CMD)" -ForegroundColor Yellow
        Write-Host "MCP Server:      http://localhost:$($ports.MCP)" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "PostgreSQL:      localhost:$($ports.POSTGRES)" -ForegroundColor Yellow
        Write-Host "Redis:           localhost:$($ports.REDIS)" -ForegroundColor Yellow
    }
}

# MCP Server
function Show-McpLogs {
    & $DC logs -f mcp
}

function Restart-Mcp {
    & $DC restart mcp
}

# Security & Encryption
function Generate-Certificates {
    Write-Host "Generating SSL certificates for PostgreSQL..." -ForegroundColor Cyan
    
    # Check if running in Git Bash or WSL
    $bashPath = Get-Command bash -ErrorAction SilentlyContinue
    if ($bashPath) {
        & bash ./docker/timescaledb/scripts/generate-ssl-certs.sh
    } else {
        Write-Host "⚠️  Bash not found. Attempting to use WSL..." -ForegroundColor Yellow
        $wslPath = Get-Command wsl -ErrorAction SilentlyContinue
        if ($wslPath) {
            & wsl bash ./docker/timescaledb/scripts/generate-ssl-certs.sh
        } else {
            Write-Host "❌ Error: Neither Git Bash nor WSL found!" -ForegroundColor Red
            Write-Host "   Please install Git for Windows or WSL to generate certificates." -ForegroundColor Yellow
            Write-Host "   Or manually create certificates in: ./docker/timescaledb/ssl/" -ForegroundColor Yellow
            return
        }
    }
    
    Write-Host "✅ Certificates generated. Restart TimescaleDB to apply:" -ForegroundColor Green
    Write-Host "   .\dev.ps1 restart" -ForegroundColor Yellow
}

function Verify-Encryption {
    Write-Host "=== Verifying Database Encryption ===" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Checking SSL status..." -ForegroundColor Yellow
    & $DC exec timescaledb psql -U reporting_user -d reporting -c "SHOW ssl;"
    Write-Host ""
    Write-Host "Checking password encryption..." -ForegroundColor Yellow
    & $DC exec timescaledb psql -U reporting_user -d reporting -c "SHOW password_encryption;"
    Write-Host ""
    Write-Host "Checking active SSL connections..." -ForegroundColor Yellow
    & $DC exec timescaledb psql -U reporting_user -d reporting -c "SELECT datname, usename, ssl, version, cipher FROM pg_stat_ssl JOIN pg_stat_activity ON pg_stat_ssl.pid = pg_stat_activity.pid WHERE datname = 'reporting';"
    Write-Host ""
    Write-Host "✅ Encryption verification complete" -ForegroundColor Green
}

function Verify-DatabaseSSL {
    Write-Host "Verifying SSL connection from application..." -ForegroundColor Cyan
    & $DC exec app php -r "`$pdo = new PDO('pgsql:host=timescaledb;dbname=reporting', 'reporting_user', 'reporting_password'); `$result = `$pdo->query('SELECT version FROM pg_stat_ssl WHERE pid = pg_backend_pid()')->fetch(); echo 'SSL Version: ' . (`$result['version'] ?? 'Not connected via SSL') . PHP_EOL;"
}

# Command Router
switch ($Command.ToLower()) {
    "help" { Show-Help }
    
    # Docker Management
    "up" { Start-Services }
    "down" { Stop-Services }
    "restart" { Restart-Services }
    "build" { Build-Services }
    "logs" { Show-Logs }
    "logs-app" { Show-AppLogs }
    "logs-queue" { Show-QueueLogs }
    "logs-scheduler" { Show-SchedulerLogs }
    "logs-nginx" { Show-NginxLogs }
    
    # Application
    "install" { Install-Application }
    "shell" { Open-Shell }
    "artisan" { Run-Artisan }
    "migrate" { Run-Migrate }
    "migrate-fresh" { Run-MigrateFresh }
    "seed" { Run-Seed }
    "fresh" { Run-Fresh }
    "test" { Run-Tests }
    "cache-clear" { Clear-Cache }
    "optimize" { Optimize-Application }
    
    # Database
    "db-shell" { Open-DatabaseShell }
    "db-backup" { Backup-Database }
    "db-restore" { Restore-Database }
    "db-verify-ssl" { Verify-DatabaseSSL }
    
    # Redis
    "redis-cli" { Open-RedisCli }
    "redis-flush" { Flush-Redis }
    
    # Frontend
    "frontend-install" { Install-Frontend }
    "frontend-build" { Build-Frontend }
    "frontend-dev" { Start-FrontendDev }
    
    # Queue & Scheduler
    "queue-restart" { Restart-Queue }
    "queue-work" { Run-QueueWork }
    "scheduler-run" { Run-Scheduler }
    
    # Cleanup
    "clean" { Clean-All }
    "clean-cache" { Clean-CacheFiles }
    
    # Development Helpers
    "tinker" { Open-Tinker }
    "routes" { Show-Routes }
    "models" { Show-Models }
    "status" { Show-Status }
    
    # MCP
    "mcp-logs" { Show-McpLogs }
    "mcp-restart" { Restart-Mcp }
    
    # Security
    "generate-certs" { Generate-Certificates }
    "verify-encryption" { Verify-Encryption }
    
    default {
        Write-Host "Unknown command: $Command" -ForegroundColor Red
        Write-Host ""
        Show-Help
    }
}
