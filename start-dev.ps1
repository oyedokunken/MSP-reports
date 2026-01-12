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
Write-Host "🚀 Starting Laravel Workers..."
Write-Host "The window will stay open to keep workers running." -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop all workers." -ForegroundColor Yellow

# Ensure we are in the backend directory
if (Test-Path "backend") {
    Set-Location "backend"
}

$backendPath = (Get-Location).Path
$jobs = @()

# Function to start a worker job
function Start-LaravelJob {
    param($name, $command)
    Write-Host "Starting $name..."
    return Start-Job -Name $name -ScriptBlock {
        param($path, $cmd)
        Set-Location $path
        # Run php artisan via cmd to ensure separate process context
        Invoke-Expression "php artisan $cmd"
    } -ArgumentList $backendPath, $command
}

$jobs += Start-LaravelJob "Worker-1" "queue:work --queue=high,default,low"
$jobs += Start-LaravelJob "Worker-2" "queue:work --queue=high,default,low"
$jobs += Start-LaravelJob "Worker-3" "queue:work --queue=high,default,low"
$jobs += Start-LaravelJob "Scheduler" "schedule:work"

Write-Host ""
Write-Host "✅ All workers started." -ForegroundColor Green
Write-Host "Monitoring workers (errors will appear here)..." -ForegroundColor Gray

try {
    # Keep the script running and output job errors if they finish
    while ($true) {
        foreach ($job in $jobs) {
            if ($job.State -ne "Running" -and $job.State -ne "Completed") {
                Write-Host "⚠️  Job $($job.Name) is in state: $($job.State)" -ForegroundColor Yellow
            }
            # Receive results (errors or output)
            Receive-Job -Job $job
        }
        Start-Sleep -Seconds 2
    }
}
finally {
    Write-Host "`nStopping all workers..." -ForegroundColor Yellow
    foreach ($job in $jobs) {
        Stop-Job -Job $job -ErrorAction SilentlyContinue
        Remove-Job -Job $job -ErrorAction SilentlyContinue
    }
    Write-Host "✅ All workers stopped." -ForegroundColor Green
}
