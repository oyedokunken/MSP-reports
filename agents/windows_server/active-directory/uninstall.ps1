<#
.SYNOPSIS
    Uninstallation script for AD Sync Agent

.DESCRIPTION
    Removes the AD Sync Agent scheduled task or Windows service

.PARAMETER RemoveScheduledTask
    Remove the scheduled task

.PARAMETER RemoveService
    Remove the Windows service

.PARAMETER RemoveCredentials
    Remove stored credentials from Windows Credential Manager

.PARAMETER RemoveLogs
    Remove log files
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory=$false)]
    [switch]$RemoveScheduledTask,
    
    [Parameter(Mandatory=$false)]
    [switch]$RemoveService,
    
    [Parameter(Mandatory=$false)]
    [switch]$RemoveCredentials,
    
    [Parameter(Mandatory=$false)]
    [switch]$RemoveLogs
)

$ErrorActionPreference = "Stop"

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=== MSP Reports AD Sync Agent - Uninstallation ===" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$IsAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $IsAdmin) {
    Write-Error "This script must be run as Administrator"
    exit 1
}

# Remove scheduled task
if ($RemoveScheduledTask) {
    Write-Host "=== Removing Scheduled Task ===" -ForegroundColor Cyan
    
    $TaskName = "MSP Reports - AD Sync"
    
    try {
        $Task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
        if ($Task) {
            # Stop task if running
            Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
            
            # Unregister task
            Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
            
            Write-Host "✓ Scheduled task removed: $TaskName" -ForegroundColor Green
        } else {
            Write-Host "  Task not found: $TaskName" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "✗ Failed to remove scheduled task: $_" -ForegroundColor Red
    }
}

# Remove Windows service
if ($RemoveService) {
    Write-Host ""
    Write-Host "=== Removing Windows Service ===" -ForegroundColor Cyan
    
    $ServiceName = "MSPReportsADSync"
    
    try {
        $Service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if ($Service) {
            # Stop service
            if ($Service.Status -eq 'Running') {
                Write-Host "  Stopping service..." -ForegroundColor Yellow
                Stop-Service -Name $ServiceName -Force
            }
            
            # Check for NSSM
            $NSSMPath = "C:\Program Files\NSSM\nssm.exe"
            if (Test-Path $NSSMPath) {
                & $NSSMPath remove $ServiceName confirm
                Write-Host "✓ Windows service removed: $ServiceName" -ForegroundColor Green
            } else {
                # Fallback to sc.exe
                sc.exe delete $ServiceName
                Write-Host "✓ Windows service removed: $ServiceName" -ForegroundColor Green
            }
        } else {
            Write-Host "  Service not found: $ServiceName" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "✗ Failed to remove service: $_" -ForegroundColor Red
    }
}

# Remove credentials
if ($RemoveCredentials) {
    Write-Host ""
    Write-Host "=== Removing Stored Credentials ===" -ForegroundColor Cyan
    
    try {
        Import-Module "$ScriptDir\modules\APIClient.psm1" -Force
        
        $Result = Remove-StoredCredential -Target "MSPReports-AD-Agent"
        
        if ($Result) {
            Write-Host "✓ Stored credentials removed" -ForegroundColor Green
        } else {
            Write-Host "  No credentials found" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "✗ Failed to remove credentials: $_" -ForegroundColor Red
    }
}

# Remove logs
if ($RemoveLogs) {
    Write-Host ""
    Write-Host "=== Removing Log Files ===" -ForegroundColor Cyan
    
    try {
        $ConfigPath = Join-Path $ScriptDir "config.json"
        if (Test-Path $ConfigPath) {
            $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
            $LogPath = $Config.logging.logPath
            
            if ($LogPath -and (Test-Path $LogPath)) {
                $Confirm = Read-Host "Remove all logs from $LogPath? (Y/N)"
                if ($Confirm -eq 'Y' -or $Confirm -eq 'y') {
                    Remove-Item -Path "$LogPath\*" -Force -Recurse
                    Write-Host "✓ Log files removed from: $LogPath" -ForegroundColor Green
                } else {
                    Write-Host "  Log removal cancelled" -ForegroundColor Yellow
                }
            } else {
                Write-Host "  No log directory found" -ForegroundColor Yellow
            }
        }
    } catch {
        Write-Host "✗ Failed to remove logs: $_" -ForegroundColor Red
    }
}

# Remove sync state file
try {
    $StateFile = Join-Path $ScriptDir "config.state.json"
    if (Test-Path $StateFile) {
        Remove-Item $StateFile -Force
        Write-Host "✓ Sync state file removed" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Failed to remove sync state file: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== Uninstallation Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "Note: Configuration file (config.json) and agent files have been preserved." -ForegroundColor Gray
Write-Host "To completely remove the agent, delete the directory: $ScriptDir" -ForegroundColor Gray
Write-Host ""
