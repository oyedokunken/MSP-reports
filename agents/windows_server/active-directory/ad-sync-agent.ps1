<#
.SYNOPSIS
    MSP Reports - Active Directory Sync Agent for MSP Reports Platform

.DESCRIPTION
    Collects Active Directory users, security groups, and license usage data
    and pushes it to the MSP Reports Platform API.

.PARAMETER ConfigPath
    Path to the configuration JSON file

.PARAMETER FullSync
    Force a full sync, ignoring delta sync state

.PARAMETER DryRun
    Collect data but don't push to API (for testing)

.EXAMPLE
    .\ad-sync-agent.ps1 -ConfigPath ".\config.json"

.EXAMPLE
    .\ad-sync-agent.ps1 -ConfigPath ".\config.json" -FullSync

.EXAMPLE
    .\ad-sync-agent.ps1 -ConfigPath ".\config.json" -DryRun
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = ".\config.json",
    
    [Parameter(Mandatory=$false)]
    [switch]$FullSync,
    
    [Parameter(Mandatory=$false)]
    [switch]$DryRun
)

# Set error action preference
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Import modules
try {
    Import-Module "$ScriptDir\modules\Logger.psm1" -Force
    Import-Module "$ScriptDir\modules\ADDataCollector.psm1" -Force
    Import-Module "$ScriptDir\modules\APIClient.psm1" -Force
    Import-Module ActiveDirectory -ErrorAction Stop
} catch {
    Write-Error "Failed to import required modules: $_"
    exit 1
}

# Main execution
try {
    # Initialize logger
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
    Initialize-Logger -Config $Config.logging
    
    Write-Log "INFO" "=== MSP Reports - Active Directory Sync Agent Started ==="
    Write-Log "INFO" "Version: 1.0.0"
    Write-Log "INFO" "Config: $ConfigPath"
    Write-Log "INFO" "Full Sync: $FullSync"
    Write-Log "INFO" "Dry Run: $DryRun"
    
    # Validate configuration
    Write-Log "INFO" "Validating configuration..."
    if (-not $Config.platform.apiUrl) {
        throw "API URL not configured"
    }
    if (-not $Config.platform.companyId) {
        throw "Company ID not configured"
    }
    
    # Get API key from secure store or config
    $ApiKey = $null
    if ($Config.advanced.useSecureCredentialStore) {
        Write-Log "INFO" "Retrieving API key from Windows Credential Manager..."
        $ApiKey = Get-StoredCredential -Target "MSPReports-AD-Agent"
    }
    if (-not $ApiKey) {
        Write-Log "WARN" "Using API key from config file (not recommended for production)"
        $ApiKey = $Config.platform.apiKey
    }
    if (-not $ApiKey) {
        throw "API key not configured or found in credential store"
    }
    
    # Test API connectivity
    Write-Log "INFO" "Testing API connectivity..."
    if (-not $DryRun) {
        $connectionTest = Test-APIConnection -ApiUrl $Config.platform.apiUrl -ApiKey $ApiKey
        if (-not $connectionTest.Success) {
            throw "API connection test failed: $($connectionTest.Error)"
        }
        Write-Log "INFO" "API connection successful"
    }
    
    # Determine sync mode
    $SyncState = Get-SyncState -ConfigPath $ConfigPath
    $IsDeltaSync = $Config.sync.enableDeltaSync -and (-not $FullSync) -and $SyncState.LastSuccessfulSync
    
    if ($IsDeltaSync) {
        Write-Log "INFO" "Performing delta sync since: $($SyncState.LastSuccessfulSync)"
        $LastSyncTime = [DateTime]::Parse($SyncState.LastSuccessfulSync)
    } else {
        Write-Log "INFO" "Performing full sync"
        $LastSyncTime = $null
    }
    
    # Collect AD Users
    Write-Log "INFO" "Collecting Active Directory users..."
    $Users = Get-ADUsersData -Config $Config.activeDirectory -LastSyncTime $LastSyncTime
    Write-Log "INFO" "Collected $($Users.Count) users"
    
    # Collect Security Groups
    Write-Log "INFO" "Collecting security groups..."
    $Groups = Get-ADSecurityGroups -Config $Config.activeDirectory -LastSyncTime $LastSyncTime
    Write-Log "INFO" "Collected $($Groups.Count) security groups"
    
    # Collect Group Memberships
    Write-Log "INFO" "Collecting group memberships..."
    $GroupMemberships = Get-ADGroupMemberships -Groups $Groups -Config $Config.activeDirectory
    Write-Log "INFO" "Collected $($GroupMemberships.Count) group membership records"
    
    # Prepare payload
    Write-Log "INFO" "Preparing sync payload..."
    $Payload = @{
        companyId = $Config.platform.companyId
        integrationId = $Config.platform.integrationId
        syncType = if ($IsDeltaSync) { "delta" } else { "full" }
        syncTimestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
        domain = $Config.activeDirectory.domain
        data = @{
            users = $Users
            securityGroups = $Groups
            groupMemberships = $GroupMemberships
        }
        statistics = @{
            userCount = $Users.Count
            groupCount = $Groups.Count
            membershipCount = $GroupMemberships.Count
        }
    }
    
    # Compress payload if enabled
    if ($Config.sync.compressionEnabled) {
        Write-Log "INFO" "Compressing payload..."
        $PayloadJson = $Payload | ConvertTo-Json -Depth 10 -Compress
        $PayloadSize = [System.Text.Encoding]::UTF8.GetByteCount($PayloadJson)
        Write-Log "INFO" "Payload size: $([Math]::Round($PayloadSize / 1KB, 2)) KB"
    }
    
    # Push to API
    if ($DryRun) {
        Write-Log "INFO" "DRY RUN: Skipping API push"
        Write-Log "INFO" "Would have sent:"
        Write-Log "INFO" "  - $($Users.Count) users"
        Write-Log "INFO" "  - $($Groups.Count) groups"
        Write-Log "INFO" "  - $($GroupMemberships.Count) memberships"
    } else {
        Write-Log "INFO" "Pushing data to API..."
        
        $PushResult = Push-ADDataToAPI `
            -ApiUrl $Config.platform.apiUrl `
            -ApiKey $ApiKey `
            -Payload $Payload `
            -MaxRetries $Config.sync.maxRetries `
            -RetryDelay $Config.sync.retryDelaySeconds
        
        if ($PushResult.Success) {
            Write-Log "INFO" "Data pushed successfully"
            Write-Log "INFO" "Response: $($PushResult.Message)"
            
            # Update sync state
            Save-SyncState -ConfigPath $ConfigPath -Success $true -Message $PushResult.Message
        } else {
            throw "Failed to push data to API: $($PushResult.Error)"
        }
    }
    
    Write-Log "INFO" "=== Active Directory Sync Agent Completed Successfully ==="
    
    # Cleanup old logs
    if ($Config.logging.retentionDays -gt 0) {
        Clear-OldLogs -LogPath $Config.logging.logPath -RetentionDays $Config.logging.retentionDays
    }
    
    exit 0
    
} catch {
    Write-Log "ERROR" "Sync failed: $_"
    Write-Log "ERROR" "Stack trace: $($_.ScriptStackTrace)"
    
    # Update sync state with failure
    try {
        Save-SyncState -ConfigPath $ConfigPath -Success $false -Message $_.Exception.Message
    } catch {
        Write-Log "ERROR" "Failed to save sync state: $_"
    }
    
    Write-Log "ERROR" "=== Active Directory Sync Agent Failed ==="
    exit 1
}
