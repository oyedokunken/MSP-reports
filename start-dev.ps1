# Windows Dev Start Script (start-dev.ps1)
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
Write-Host "Press Ctrl+C to stop"

Set-Location "backend"

$jobs = @()

Write-Host "Starting Worker 1..."
$jobs += Start-Job -ScriptBlock { php artisan queue:work --queue=high,default,low }

Write-Host "Starting Worker 2..."
$jobs += Start-Job -ScriptBlock { php artisan queue:work --queue=high,default,low }

Write-Host "Starting Worker 3..."
$jobs += Start-Job -ScriptBlock { php artisan queue:work --queue=high,default,low }

Write-Host "Starting Scheduler..."
$jobs += Start-Job -ScriptBlock { php artisan schedule:work }

Write-Host "workers started. Monitoring..."

try {
    while ($true) {
        foreach ($job in $jobs) {
            Receive-Job -Job $job -Keep
        }
        Start-Sleep -Seconds 1
    }
}
finally {
    Write-Host "Stopping all workers..."
    foreach ($job in $jobs) {
        Stop-Job -Job $job
        Remove-Job -Job $job
    }
    Write-Host "All workers stopped."
}
