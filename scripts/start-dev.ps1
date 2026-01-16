# Windows Dev Start Script (start-dev.ps1)
$ErrorActionPreference = "Stop"

Write-Host "🚀 Starting Development Environment..." -ForegroundColor Cyan

function Check-Service {
    param($name)
    if (Get-Service $name -ErrorAction SilentlyContinue | Where-Object {$_.Status -eq 'Running'}) {
        Write-Host "✅ $name is running" -ForegroundColor Green
    } else {
        Write-Host "⚠️  $name is NOT running (or not found as service name)." -ForegroundColor Yellow
    }
}

Check-Service "postgresql*" 
Check-Service "redis*"

Write-Host "--------------------------------"
Write-Host "🚀 Starting Laravel Workers in Docker..." -ForegroundColor Cyan

# Ensure we are in the backend directory
if (Test-Path "backend") {
    $originalDir = Get-Location
    Set-Location "backend"
    
    # Check if Docker is running
    try {
        docker ps > $null
    } catch {
        Write-Host "❌ Docker is NOT running. Please start Docker Desktop." -ForegroundColor Red
        Set-Location $originalDir
        exit
    }

    Write-Host "Starting workers and Gotenberg..." -ForegroundColor Gray
    docker compose up -d --build --scale queue-worker=3
    
    Write-Host ""
    Write-Host "✅ Workers and Gotenberg are running in Docker." -ForegroundColor Green
    Write-Host "   - 3 Queue Workers" -ForegroundColor Gray
    Write-Host "   - 1 Scheduler" -ForegroundColor Gray
    Write-Host "   - Gotenberg" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Logs: docker compose logs -f" -ForegroundColor Cyan
} else {
    Write-Host "❌ Backend directory not found." -ForegroundColor Red
    exit
}

Write-Host "Press Ctrl+C to stop all workers and exit." -ForegroundColor Yellow

# Keep this script running to allow easy Ctrl+C to stop
try {
    while ($true) {
        Start-Sleep -Seconds 10
    }
}
finally {
    Write-Host "`n🛑 Stopping Docker containers..." -ForegroundColor Yellow
    docker compose stop
    Write-Host "Done." -ForegroundColor Gray
}
