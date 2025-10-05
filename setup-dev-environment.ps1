# PowerShell script for Windows Server 2025
# Multi-Developer Environment Setup

Write-Host "=== Multi-Developer Environment Setup ===" -ForegroundColor Cyan
Write-Host ""

# Prompt for developer ID
$DEV_ID = Read-Host "Enter your developer ID (1-20)"

# Validate input
if (-not ($DEV_ID -match '^\d+$') -or [int]$DEV_ID -lt 1 -or [int]$DEV_ID -gt 20) {
    Write-Host "❌ Invalid developer ID. Must be between 1 and 20." -ForegroundColor Red
    exit 1
}

$DEV_ID = [int]$DEV_ID

# Calculate ports
$NGINX_PORT = 8000 + $DEV_ID
$POSTGRES_PORT = 5432 + $DEV_ID
$REDIS_PORT = 6379 + $DEV_ID
$FRONTEND_PORT = 5173 + $DEV_ID
$MAILHOG_SMTP_PORT = 1025 + $DEV_ID
$MAILHOG_UI_PORT = 8025 + $DEV_ID
$PGADMIN_PORT = 5050 + $DEV_ID
$REDIS_CMD_PORT = 8081 + $DEV_ID
$MCP_PORT = 3000 + $DEV_ID
$HTTPS_PORT = $NGINX_PORT + 400

# Create docker-compose.override.yml
$overrideContent = @"
version: '3.8'

# Auto-generated for Developer $DEV_ID
# Do not commit this file (it's in .gitignore)

services:
  nginx:
    container_name: reporting-nginx-dev$DEV_ID
    ports:
      - "$($NGINX_PORT):80"
      - "$($HTTPS_PORT):443"
  
  app:
    container_name: reporting-app-dev$DEV_ID
  
  timescaledb:
    container_name: reporting-timescaledb-dev$DEV_ID
    ports:
      - "$($POSTGRES_PORT):5432"
  
  redis:
    container_name: reporting-redis-dev$DEV_ID
    ports:
      - "$($REDIS_PORT):6379"
  
  queue:
    container_name: reporting-queue-dev$DEV_ID
  
  scheduler:
    container_name: reporting-scheduler-dev$DEV_ID
  
  mcp:
    container_name: reporting-mcp-dev$DEV_ID
    ports:
      - "$($MCP_PORT):3000"
  
  frontend:
    container_name: reporting-frontend-dev$DEV_ID
    ports:
      - "$($FRONTEND_PORT):5173"
    environment:
      - VITE_API_URL=http://localhost:$($NGINX_PORT)/api
  
  mailhog:
    container_name: reporting-mailhog-dev$DEV_ID
    ports:
      - "$($MAILHOG_SMTP_PORT):1025"
      - "$($MAILHOG_UI_PORT):8025"
  
  pgadmin:
    container_name: reporting-pgadmin-dev$DEV_ID
    ports:
      - "$($PGADMIN_PORT):80"
  
  redis-commander:
    container_name: reporting-redis-commander-dev$DEV_ID
    ports:
      - "$($REDIS_CMD_PORT):8081"

networks:
  reporting-network:
    name: reporting-network-dev$DEV_ID

volumes:
  timescaledb-data:
    name: reporting-timescaledb-data-dev$DEV_ID
  redis-data:
    name: reporting-redis-data-dev$DEV_ID
  pgadmin-data:
    name: reporting-pgadmin-data-dev$DEV_ID
  frontend-node-modules:
    name: reporting-frontend-node-modules-dev$DEV_ID
"@

Set-Content -Path "docker-compose.override.yml" -Value $overrideContent

# Create .env.local with developer-specific settings
$envContent = @"
# Developer $DEV_ID Environment
DEVELOPER_ID=$DEV_ID

# Update these in your .env file if needed
APP_URL=http://localhost:$NGINX_PORT
VITE_API_URL=http://localhost:$($NGINX_PORT)/api
"@

Set-Content -Path ".env.local" -Value $envContent

Write-Host ""
Write-Host "✅ Environment configured for Developer $DEV_ID" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Your service URLs:" -ForegroundColor Yellow
Write-Host "  Frontend:        http://localhost:$FRONTEND_PORT"
Write-Host "  API:             http://localhost:$($NGINX_PORT)/api"
Write-Host "  MailHog:         http://localhost:$MAILHOG_UI_PORT"
Write-Host "  pgAdmin:         http://localhost:$PGADMIN_PORT"
Write-Host "  Redis Commander: http://localhost:$REDIS_CMD_PORT"
Write-Host "  MCP Server:      localhost:$MCP_PORT"
Write-Host ""
Write-Host "🗄️  PostgreSQL connection:" -ForegroundColor Yellow
Write-Host "  Host: localhost"
Write-Host "  Port: $POSTGRES_PORT"
Write-Host "  Database: reporting"
Write-Host "  User: reporting_user"
Write-Host ""
Write-Host "📝 Next steps:" -ForegroundColor Cyan
Write-Host "  1. Copy .env.example to .env (if not done already)"
Write-Host "     Copy-Item .env.example .env"
Write-Host "  2. Start Docker services:"
Write-Host "     docker-compose up -d"
Write-Host "  3. Install dependencies:"
Write-Host "     docker-compose exec app composer install"
Write-Host "     docker-compose exec app php artisan key:generate"
Write-Host "     docker-compose exec app php artisan migrate"
Write-Host "  4. Access your services at the URLs above"
Write-Host ""
Write-Host "💡 Tip: Save these URLs for easy access!" -ForegroundColor Green
Write-Host ""
