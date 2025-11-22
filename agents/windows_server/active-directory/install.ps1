<#
.SYNOPSIS
    Installation script for MSP Reports AD Sync Agent

.DESCRIPTION
    Installs the AD Sync Agent as a scheduled task or Windows service

.PARAMETER InstallScheduledTask
    Install as a scheduled task

.PARAMETER InstallService
    Install as a Windows service (requires NSSM)

.PARAMETER StoreCredentials
    Store API credentials in Windows Credential Manager

.PARAMETER ServiceAccount
    Account to run the service/task as (defaults to SYSTEM)
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory=$false)]
    [switch]$InstallScheduledTask,
    
    [Parameter(Mandatory=$false)]
    [switch]$InstallService,
    
    [Parameter(Mandatory=$false)]
    [switch]$StoreCredentials,
    
    [Parameter(Mandatory=$false)]
    [string]$ServiceAccount = "SYSTEM"
)

$ErrorActionPreference = "Stop"

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=== MSP Reports AD Sync Agent - Installation ===" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$IsAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $IsAdmin) {
    Write-Error "This script must be run as Administrator"
    exit 1
}

# Check if ActiveDirectory module is available
try {
    Import-Module ActiveDirectory -ErrorAction Stop
    Write-Host "✓ Active Directory PowerShell module found" -ForegroundColor Green
} catch {
    Write-Host "✗ Active Directory PowerShell module not found" -ForegroundColor Red
    Write-Host "  Install with: Install-WindowsFeature RSAT-AD-PowerShell" -ForegroundColor Yellow
    exit 1
}

# Check for config file
$ConfigPath = Join-Path $ScriptDir "config.json"
if (-not (Test-Path $ConfigPath)) {
    Write-Host "✗ Configuration file not found: $ConfigPath" -ForegroundColor Red
    Write-Host "  Copy config.example.json to config.json and edit it" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ Configuration file found" -ForegroundColor Green

# Validate config
try {
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
    
    if (-not $Config.platform.apiUrl) {
        throw "API URL not configured"
    }
    if (-not $Config.platform.companyId) {
        throw "Company ID not configured"
    }
    
    Write-Host "✓ Configuration validated" -ForegroundColor Green
} catch {
    Write-Host "✗ Configuration validation failed: $_" -ForegroundColor Red
    exit 1
}

# Store credentials if requested
if ($StoreCredentials) {
    Write-Host ""
    Write-Host "=== Store API Credentials ===" -ForegroundColor Cyan
    
    Import-Module "$ScriptDir\modules\APIClient.psm1" -Force
    
    $ApiKey = Read-Host "Enter API Key" -AsSecureString
    $ApiKeyPlain = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($ApiKey))
    
    $Result = Set-StoredCredential -Target "MSPReports-AD-Agent" -Username "apikey" -Password $ApiKeyPlain
    
    if ($Result) {
        Write-Host "✓ API credentials stored in Windows Credential Manager" -ForegroundColor Green
    } else {
        Write-Host "✗ Failed to store credentials" -ForegroundColor Red
        exit 1
    }
}

# Create log directory
$LogPath = $Config.logging.logPath
if ($LogPath -and -not (Test-Path $LogPath)) {
    New-Item -ItemType Directory -Path $LogPath -Force | Out-Null
    Write-Host "✓ Log directory created: $LogPath" -ForegroundColor Green
}

# Install as scheduled task
if ($InstallScheduledTask) {
    Write-Host ""
    Write-Host "=== Installing as Scheduled Task ===" -ForegroundColor Cyan
    
    $TaskName = "MSP Reports - AD Sync"
    $ScriptPath = Join-Path $ScriptDir "ad-sync-agent.ps1"
    
    # Check if task already exists
    $ExistingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($ExistingTask) {
        Write-Host "  Task already exists. Removing..." -ForegroundColor Yellow
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    }
    
    # Create action
    $Action = New-ScheduledTaskAction `
        -Execute "PowerShell.exe" `
        -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`"" `
        -WorkingDirectory $ScriptDir
    
    # Create trigger (every X minutes from config, default to 240)
    $IntervalMinutes = if ($Config.sync.intervalMinutes) { $Config.sync.intervalMinutes } else { 240 }
    $Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes)
    
    # Create settings
    $Settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -RunOnlyIfNetworkAvailable `
        -ExecutionTimeLimit (New-TimeSpan -Hours 2)
    
    # Create principal
    if ($ServiceAccount -eq "SYSTEM") {
        $Principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    } else {
        $Principal = New-ScheduledTaskPrincipal -UserId $ServiceAccount -LogonType ServiceAccount -RunLevel Highest
    }
    
    # Register task
    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $Action `
        -Trigger $Trigger `
        -Settings $Settings `
        -Principal $Principal `
        -Description "Syncs Active Directory data to MSP Reports Platform" | Out-Null
    
    Write-Host "✓ Scheduled task created: $TaskName" -ForegroundColor Green
    Write-Host "  Interval: Every $IntervalMinutes minutes" -ForegroundColor Gray
    Write-Host "  Account: $ServiceAccount" -ForegroundColor Gray
    
    # Start task immediately for testing
    Write-Host ""
    $TestRun = Read-Host "Run test sync now? (Y/N)"
    if ($TestRun -eq 'Y' -or $TestRun -eq 'y') {
        Write-Host "  Starting test sync..." -ForegroundColor Yellow
        Start-ScheduledTask -TaskName $TaskName
        Start-Sleep -Seconds 3
        
        $TaskInfo = Get-ScheduledTaskInfo -TaskName $TaskName
        Write-Host "  Task state: $($TaskInfo.LastTaskResult)" -ForegroundColor Gray
        Write-Host "  Check logs at: $LogPath" -ForegroundColor Gray
    }
}

# Install as Windows service
if ($InstallService) {
    Write-Host ""
    Write-Host "=== Installing as Windows Service ===" -ForegroundColor Cyan
    
    # Check for NSSM
    $NSSMPath = "C:\Program Files\NSSM\nssm.exe"
    if (-not (Test-Path $NSSMPath)) {
        Write-Host "  NSSM not found. Downloading..." -ForegroundColor Yellow
        
        $NSSMUrl = "https://nssm.cc/release/nssm-2.24.zip"
        $TempZip = "$env:TEMP\nssm.zip"
        $TempExtract = "$env:TEMP\nssm"
        
        Invoke-WebRequest -Uri $NSSMUrl -OutFile $TempZip
        Expand-Archive -Path $TempZip -DestinationPath $TempExtract -Force
        
        # Copy appropriate version
        $NSSMDir = "C:\Program Files\NSSM"
        New-Item -ItemType Directory -Path $NSSMDir -Force | Out-Null
        
        if ([Environment]::Is64BitOperatingSystem) {
            Copy-Item "$TempExtract\nssm-2.24\win64\nssm.exe" $NSSMPath -Force
        } else {
            Copy-Item "$TempExtract\nssm-2.24\win32\nssm.exe" $NSSMPath -Force
        }
        
        Remove-Item $TempZip -Force
        Remove-Item $TempExtract -Recurse -Force
        
        Write-Host "  ✓ NSSM installed" -ForegroundColor Green
    }
    
    $ServiceName = "MSPReportsADSync"
    $ScriptPath = Join-Path $ScriptDir "ad-sync-agent.ps1"
    
    # Check if service already exists
    $ExistingService = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($ExistingService) {
        Write-Host "  Service already exists. Removing..." -ForegroundColor Yellow
        & $NSSMPath stop $ServiceName
        & $NSSMPath remove $ServiceName confirm
    }
    
    # Install service
    & $NSSMPath install $ServiceName "PowerShell.exe" "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`""
    & $NSSMPath set $ServiceName AppDirectory $ScriptDir
    & $NSSMPath set $ServiceName DisplayName "MSP Reports AD Sync Agent"
    & $NSSMPath set $ServiceName Description "Syncs Active Directory data to MSP Reports Platform"
    & $NSSMPath set $ServiceName Start SERVICE_AUTO_START
    & $NSSMPath set $ServiceName AppThrottle 10000
    & $NSSMPath set $ServiceName AppStopMethodConsole 30000
    
    # Set service account
    if ($ServiceAccount -ne "SYSTEM") {
        $Credential = Get-Credential -UserName $ServiceAccount -Message "Enter password for service account"
        & $NSSMPath set $ServiceName ObjectName $ServiceAccount $Credential.GetNetworkCredential().Password
    }
    
    Write-Host "✓ Windows service created: $ServiceName" -ForegroundColor Green
    Write-Host "  Account: $ServiceAccount" -ForegroundColor Gray
    
    # Start service
    Write-Host ""
    $StartService = Read-Host "Start service now? (Y/N)"
    if ($StartService -eq 'Y' -or $StartService -eq 'y') {
        Start-Service -Name $ServiceName
        Write-Host "  ✓ Service started" -ForegroundColor Green
        Write-Host "  Check logs at: $LogPath" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "=== Installation Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Review configuration in: $ConfigPath"
Write-Host "2. Test manually: .\ad-sync-agent.ps1 -ConfigPath config.json -DryRun"
Write-Host "3. Check logs: Get-Content '$LogPath\ad-sync-agent-*.log' -Tail 50"
Write-Host "4. Monitor sync: Check MSP Reports Platform dashboard"
Write-Host ""
