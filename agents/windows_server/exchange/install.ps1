<#
.SYNOPSIS
    Installation script for Exchange Sync Agent

.DESCRIPTION
    Helps with:
    - Storing API key securely in Windows Credential Manager
    - Installing agent as scheduled task
    - Testing agent configuration

.PARAMETER StoreCredentials
    Store the API key in Windows Credential Manager

.PARAMETER InstallScheduledTask
    Install the agent as a Windows Scheduled Task

.PARAMETER UninstallScheduledTask
    Remove the scheduled task

.PARAMETER TestConfiguration
    Test the agent configuration and connection

.EXAMPLE
    .\install.ps1 -StoreCredentials
    .\install.ps1 -InstallScheduledTask
    .\install.ps1 -TestConfiguration
#>

param(
    [switch]$StoreCredentials,
    [switch]$InstallScheduledTask,
    [switch]$UninstallScheduledTask,
    [switch]$TestConfiguration
)

$ErrorActionPreference = "Stop"

function Store-ApiKey {
    Write-Host "=== Store API Key in Windows Credential Manager ===" -ForegroundColor Cyan
    Write-Host ""
    
    $apiKey = Read-Host "Enter your API Key" -AsSecureString
    $apiKeyPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiKey)
    )
    
    # Store in Windows Credential Manager
    cmdkey /generic:"MSPReports-Exchange-Agent" /user:"apikey" /pass:$apiKeyPlain | Out-Null
    
    Write-Host "✓ API Key stored securely in Windows Credential Manager" -ForegroundColor Green
    Write-Host "  Target: MSPReports-Exchange-Agent" -ForegroundColor Gray
}

function Install-ScheduledTask {
    Write-Host "=== Install Exchange Sync Agent as Scheduled Task ===" -ForegroundColor Cyan
    Write-Host ""
    
    $scriptPath = Join-Path $PSScriptRoot "exchange-sync-agent.ps1"
    $configPath = Join-Path $PSScriptRoot "config.json"
    
    if (-not (Test-Path $configPath)) {
        Write-Host "✗ Configuration file not found: $configPath" -ForegroundColor Red
        Write-Host "  Please copy config.example.json to config.json and configure it first." -ForegroundColor Yellow
        return
    }
    
    # Load config to get sync interval
    $config = Get-Content -Path $configPath -Raw | ConvertFrom-Json
    $intervalMinutes = $config.sync.intervalMinutes
    
    Write-Host "Configuration:" -ForegroundColor Yellow
    Write-Host "  Script: $scriptPath" -ForegroundColor Gray
    Write-Host "  Config: $configPath" -ForegroundColor Gray
    Write-Host "  Interval: Every $intervalMinutes minutes" -ForegroundColor Gray
    Write-Host ""
    
    $confirm = Read-Host "Install scheduled task? (y/n)"
    if ($confirm -ne 'y') {
        Write-Host "Cancelled." -ForegroundColor Yellow
        return
    }
    
    # Create scheduled task
    $taskName = "MSPReports-Exchange-Sync"
    $taskDescription = "Sync Exchange data to MSP Reports Platform"
    
    # Action: Run PowerShell with the sync script
    $action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
        -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -ConfigPath `"$configPath`""
    
    # Trigger: Every X minutes
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes $intervalMinutes)
    
    # Settings
    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -RunOnlyIfNetworkAvailable `
        -MultipleInstances IgnoreNew
    
    # Principal: Run as SYSTEM or current user with highest privileges
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    
    # Register task
    Register-ScheduledTask -TaskName $taskName `
        -Description $taskDescription `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -Principal $principal `
        -Force
    
    Write-Host "✓ Scheduled task installed successfully" -ForegroundColor Green
    Write-Host "  Name: $taskName" -ForegroundColor Gray
    Write-Host "  Schedule: Every $intervalMinutes minutes" -ForegroundColor Gray
    Write-Host ""
    Write-Host "To run immediately: Start-ScheduledTask -TaskName '$taskName'" -ForegroundColor Yellow
}

function Uninstall-ScheduledTask {
    Write-Host "=== Uninstall Exchange Sync Agent Scheduled Task ===" -ForegroundColor Cyan
    Write-Host ""
    
    $taskName = "MSPReports-Exchange-Sync"
    
    if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Host "✓ Scheduled task removed" -ForegroundColor Green
    } else {
        Write-Host "✗ Scheduled task not found" -ForegroundColor Yellow
    }
}

function Test-Configuration {
    Write-Host "=== Test Exchange Sync Agent Configuration ===" -ForegroundColor Cyan
    Write-Host ""
    
    $scriptPath = Join-Path $PSScriptRoot "exchange-sync-agent.ps1"
    $configPath = Join-Path $PSScriptRoot "config.json"
    
    # Check files exist
    Write-Host "Checking files..." -ForegroundColor Yellow
    if (Test-Path $scriptPath) {
        Write-Host "  ✓ Agent script found" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Agent script not found: $scriptPath" -ForegroundColor Red
        return
    }
    
    if (Test-Path $configPath) {
        Write-Host "  ✓ Configuration file found" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Configuration file not found: $configPath" -ForegroundColor Red
        Write-Host "    Please copy config.example.json to config.json" -ForegroundColor Yellow
        return
    }
    
    # Load and validate config
    Write-Host ""
    Write-Host "Validating configuration..." -ForegroundColor Yellow
    try {
        $config = Get-Content -Path $configPath -Raw | ConvertFrom-Json
        
        if ($config.platform.apiUrl) {
            Write-Host "  ✓ API URL configured: $($config.platform.apiUrl)" -ForegroundColor Green
        } else {
            Write-Host "  ✗ API URL not configured" -ForegroundColor Red
        }
        
        if ($config.platform.apiKey) {
            Write-Host "  ⚠ API Key in config file (consider using Credential Manager)" -ForegroundColor Yellow
        } else {
            # Check credential manager
            $cred = cmdkey /list:"MSPReports-Exchange-Agent" 2>$null
            if ($cred -match "MSPReports-Exchange-Agent") {
                Write-Host "  ✓ API Key stored in Credential Manager" -ForegroundColor Green
            } else {
                Write-Host "  ✗ API Key not configured" -ForegroundColor Red
            }
        }
    } catch {
        Write-Host "  ✗ Invalid JSON in configuration file" -ForegroundColor Red
        Write-Host "    Error: $_" -ForegroundColor Red
        return
    }
    
    # Check Exchange Management Shell
    Write-Host ""
    Write-Host "Checking Exchange environment..." -ForegroundColor Yellow
    if (Get-Command Get-Mailbox -ErrorAction SilentlyContinue) {
        Write-Host "  ✓ Exchange Management Shell loaded" -ForegroundColor Green
        
        try {
            $org = (Get-OrganizationConfig).Name
            Write-Host "  ✓ Exchange Organization: $org" -ForegroundColor Green
        } catch {
            Write-Host "  ✗ Cannot access Exchange organization" -ForegroundColor Red
        }
    } else {
        Write-Host "  ✗ Exchange Management Shell not loaded" -ForegroundColor Red
        Write-Host "    Please run this from Exchange Management Shell" -ForegroundColor Yellow
    }
    
    # Test dry run
    Write-Host ""
    $runTest = Read-Host "Run test sync (dry run)? (y/n)"
    if ($runTest -eq 'y') {
        Write-Host ""
        Write-Host "Running test sync..." -ForegroundColor Yellow
        & $scriptPath -ConfigPath $configPath -DryRun
    }
}

# Main logic
if ($StoreCredentials) {
    Store-ApiKey
}
elseif ($InstallScheduledTask) {
    Install-ScheduledTask
}
elseif ($UninstallScheduledTask) {
    Uninstall-ScheduledTask
}
elseif ($TestConfiguration) {
    Test-Configuration
}
else {
    Write-Host "Exchange Sync Agent Installer" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\install.ps1 -StoreCredentials        # Store API key securely"
    Write-Host "  .\install.ps1 -InstallScheduledTask    # Install as scheduled task"
    Write-Host "  .\install.ps1 -UninstallScheduledTask  # Remove scheduled task"
    Write-Host "  .\install.ps1 -TestConfiguration       # Test configuration"
    Write-Host ""
    Write-Host "Quick Setup:" -ForegroundColor Yellow
    Write-Host "  1. Copy config.example.json to config.json"
    Write-Host "  2. Edit config.json with your API URL"
    Write-Host "  3. Run: .\install.ps1 -StoreCredentials"
    Write-Host "  4. Run: .\install.ps1 -InstallScheduledTask"
}
