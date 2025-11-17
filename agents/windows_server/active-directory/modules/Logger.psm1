<#
.SYNOPSIS
    Logging Module for MSP Reports AD Sync Agent

.DESCRIPTION
    Provides logging functionality to file, console, and Event Viewer
#>

# Module-level variables
$script:LogConfig = $null
$script:LogFilePath = $null
$script:EventLogSource = "MSP Reports AD Agent"
$script:EventLogName = "Application"

<#
.SYNOPSIS
    Initialize the logger

.PARAMETER Config
    Logging configuration object
#>
function Initialize-Logger {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [PSCustomObject]$Config
    )
    
    $script:LogConfig = $Config
    
    # Create log directory if it doesn't exist
    if ($Config.logPath) {
        if (-not (Test-Path $Config.logPath)) {
            New-Item -ItemType Directory -Path $Config.logPath -Force | Out-Null
        }
        
        # Set log file path
        $LogFileName = "ad-sync-agent-$(Get-Date -Format 'yyyy-MM-dd').log"
        $script:LogFilePath = Join-Path $Config.logPath $LogFileName
    }
    
    # Register Event Log source if enabled and not already registered
    if ($Config.enableEventLog) {
        try {
            if (-not [System.Diagnostics.EventLog]::SourceExists($script:EventLogSource)) {
                New-EventLog -LogName $script:EventLogName -Source $script:EventLogSource -ErrorAction SilentlyContinue
            }
        } catch {
            Write-Warning "Failed to register Event Log source. Event logging may not work: $_"
        }
    }
    
    Write-Log "INFO" "Logger initialized"
}

<#
.SYNOPSIS
    Write a log message

.PARAMETER Level
    Log level (ERROR, WARN, INFO, DEBUG)

.PARAMETER Message
    Log message
#>
function Write-Log {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [ValidateSet('ERROR', 'WARN', 'INFO', 'DEBUG')]
        [string]$Level,
        
        [Parameter(Mandatory=$true)]
        [string]$Message
    )
    
    # Check if level should be logged
    $LogLevels = @{
        'ERROR' = 0
        'WARN' = 1
        'INFO' = 2
        'DEBUG' = 3
    }
    
    $ConfiguredLevel = if ($script:LogConfig.level) { $script:LogConfig.level } else { 'INFO' }
    $ConfiguredLevelValue = $LogLevels[$ConfiguredLevel]
    $MessageLevelValue = $LogLevels[$Level]
    
    if ($MessageLevelValue -gt $ConfiguredLevelValue) {
        return
    }
    
    # Format timestamp
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    
    # Format message
    $FormattedMessage = "[$Timestamp] [$Level] $Message"
    
    # Write to console if enabled
    if ($script:LogConfig.enableConsole) {
        $Color = switch ($Level) {
            'ERROR' { 'Red' }
            'WARN' { 'Yellow' }
            'INFO' { 'White' }
            'DEBUG' { 'Gray' }
        }
        Write-Host $FormattedMessage -ForegroundColor $Color
    }
    
    # Write to file if configured
    if ($script:LogFilePath) {
        try {
            Add-Content -Path $script:LogFilePath -Value $FormattedMessage -ErrorAction SilentlyContinue
        } catch {
            # Don't fail if logging fails
            Write-Warning "Failed to write to log file: $_"
        }
    }
    
    # Write to Event Log if enabled
    if ($script:LogConfig.enableEventLog) {
        $EventType = switch ($Level) {
            'ERROR' { 'Error' }
            'WARN' { 'Warning' }
            'INFO' { 'Information' }
            'DEBUG' { 'Information' }
        }
        
        try {
            Write-EventLog `
                -LogName $script:EventLogName `
                -Source $script:EventLogSource `
                -EntryType $EventType `
                -EventId 1000 `
                -Message $Message `
                -ErrorAction SilentlyContinue
        } catch {
            # Don't fail if event logging fails
        }
    }
}

<#
.SYNOPSIS
    Clear old log files

.PARAMETER LogPath
    Path to log directory

.PARAMETER RetentionDays
    Number of days to retain logs
#>
function Clear-OldLogs {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$LogPath,
        
        [Parameter(Mandatory=$true)]
        [int]$RetentionDays
    )
    
    try {
        if (-not (Test-Path $LogPath)) {
            return
        }
        
        $CutoffDate = (Get-Date).AddDays(-$RetentionDays)
        
        Get-ChildItem -Path $LogPath -Filter "*.log" | 
            Where-Object { $_.LastWriteTime -lt $CutoffDate } |
            ForEach-Object {
                Write-Log "INFO" "Deleting old log file: $($_.Name)"
                Remove-Item $_.FullName -Force -ErrorAction SilentlyContinue
            }
            
    } catch {
        Write-Log "WARN" "Failed to clear old logs: $_"
    }
}

<#
.SYNOPSIS
    Rotate log file if it exceeds size limit

.PARAMETER MaxSizeMB
    Maximum log file size in MB
#>
function Test-LogRotation {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [int]$MaxSizeMB
    )
    
    try {
        if (-not $script:LogFilePath -or -not (Test-Path $script:LogFilePath)) {
            return
        }
        
        $LogFile = Get-Item $script:LogFilePath
        $SizeMB = $LogFile.Length / 1MB
        
        if ($SizeMB -gt $MaxSizeMB) {
            # Rotate log
            $BackupName = "$($LogFile.BaseName)-$(Get-Date -Format 'yyyyMMdd-HHmmss')$($LogFile.Extension)"
            $BackupPath = Join-Path $LogFile.DirectoryName $BackupName
            
            Move-Item -Path $script:LogFilePath -Destination $BackupPath -Force
            
            Write-Log "INFO" "Log rotated to: $BackupName"
        }
        
    } catch {
        Write-Warning "Failed to rotate log: $_"
    }
}

# Export functions
Export-ModuleMember -Function @(
    'Initialize-Logger',
    'Write-Log',
    'Clear-OldLogs',
    'Test-LogRotation'
)
