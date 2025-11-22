<#
.SYNOPSIS
    Microsoft Exchange Server Sync Agent for MSP Reports Platform

.DESCRIPTION
    Collects Exchange mailbox data including:
    - Mailboxes (user, shared, resource)
    - Email aliases
    - Mailbox permissions (FullAccess, SendAs, SendOnBehalf)
    - Mailbox statistics
    
    Sends data to MSP Reports Platform API

.PARAMETER ConfigPath
    Path to configuration JSON file

.PARAMETER FullSync
    Force a full sync instead of delta

.PARAMETER DryRun
    Collect data but don't send to API (for testing)

.EXAMPLE
    .\exchange-sync-agent.ps1 -ConfigPath ".\config.json"
    .\exchange-sync-agent.ps1 -FullSync -DryRun
#>

param(
    [string]$ConfigPath = ".\config.json",
    [switch]$FullSync,
    [switch]$DryRun
)

# Requires Exchange Management Shell
if (-not (Get-Command Get-Mailbox -ErrorAction SilentlyContinue)) {
    Write-Error "Exchange Management Shell not loaded. Please run this script from Exchange Management Shell."
    exit 1
}

#region Helper Functions

function Write-Log {
    param(
        [string]$Level,
        [string]$Message
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    if ($Config.logging.enableConsole) {
        Write-Host $logMessage
    }
    
    if ($Config.logging.logPath) {
        $logFile = Join-Path $Config.logging.logPath "exchange-sync-$(Get-Date -Format 'yyyyMMdd').log"
        $logMessage | Out-File -FilePath $logFile -Append -Encoding UTF8
    }
}

function Get-StoredCredential {
    param([string]$Target)
    
    try {
        $cred = cmdkey /list:$Target 2>$null
        if ($cred -match "Target: $Target") {
            # Read from credential manager
            $password = (New-Object PSCredential "dummy", (Get-Credential -Message "Enter API Key" -UserName "apikey").Password).GetNetworkCredential().Password
            return $password
        }
    } catch {
        return $null
    }
}

function ConvertTo-Bytes {
    param([string]$SizeString)
    
    if ([string]::IsNullOrEmpty($SizeString)) {
        return 0
    }
    
    # Parse Exchange size format like "1.5 GB (1,610,612,736 bytes)"
    if ($SizeString -match '\(([0-9,]+) bytes\)') {
        return [long]$matches[1].Replace(',', '')
    }
    
    return 0
}

#endregion

#region Data Collection Functions

function Get-ExchangeMailboxes {
    param($Config)
    
    Write-Log "INFO" "Collecting Exchange mailboxes..."
    
    $filter = "*"
    $mailboxes = @()
    
    try {
        $allMailboxes = Get-Mailbox -ResultSize Unlimited -Filter $filter | Select-Object -First $Config.exchange.maxMailboxesPerSync
        
        foreach ($mbx in $allMailboxes) {
            # Skip if filters specified
            if ($Config.exchange.filterDatabases.Count -gt 0) {
                if ($mbx.Database -notin $Config.exchange.filterDatabases) {
                    continue
                }
            }
            
            if ($Config.exchange.excludeDatabases.Count -gt 0) {
                if ($mbx.Database -in $Config.exchange.excludeDatabases) {
                    continue
                }
            }
            
            # Check mailbox type filters
            if (-not $Config.exchange.includeSharedMailboxes -and $mbx.RecipientTypeDetails -eq "SharedMailbox") {
                continue
            }
            
            if (-not $Config.exchange.includeResourceMailboxes -and 
                ($mbx.RecipientTypeDetails -eq "RoomMailbox" -or $mbx.RecipientTypeDetails -eq "EquipmentMailbox")) {
                continue
            }
            
            Write-Log "INFO" "Processing mailbox: $($mbx.PrimarySmtpAddress)"
            
            $mailboxData = @{
                guid = $mbx.Guid.ToString()
                identity = $mbx.Identity
                displayName = $mbx.DisplayName
                alias = $mbx.Alias
                primarySmtpAddress = $mbx.PrimarySmtpAddress
                userPrincipalName = $mbx.UserPrincipalName
                samAccountName = $mbx.SamAccountName
                recipientType = $mbx.RecipientType
                recipientTypeDetails = $mbx.RecipientTypeDetails
                isShared = ($mbx.RecipientTypeDetails -eq "SharedMailbox")
                isResource = ($mbx.RecipientTypeDetails -eq "RoomMailbox" -or $mbx.RecipientTypeDetails -eq "EquipmentMailbox")
                resourceType = if ($mbx.RecipientTypeDetails -eq "RoomMailbox") { "Room" } elseif ($mbx.RecipientTypeDetails -eq "EquipmentMailbox") { "Equipment" } else { $null }
                hiddenFromAddressLists = $mbx.HiddenFromAddressListsEnabled
                externalDirectoryObjectId = $mbx.ExternalDirectoryObjectId
                database = $mbx.Database
                serverName = $mbx.ServerName
                distinguishedName = $mbx.DistinguishedName
                prohibitSendQuota = ConvertTo-Bytes $mbx.ProhibitSendQuota
                prohibitSendReceiveQuota = ConvertTo-Bytes $mbx.ProhibitSendReceiveQuota
                issueWarningQuota = ConvertTo-Bytes $mbx.IssueWarningQuota
                useDatabaseQuotaDefaults = $mbx.UseDatabaseQuotaDefaults
                archiveEnabled = $mbx.ArchiveState -ne "None"
                archiveGuid = if ($mbx.ArchiveGuid) { $mbx.ArchiveGuid.ToString() } else { $null }
                archiveName = $mbx.ArchiveName
                archiveDatabase = $mbx.ArchiveDatabase
                archiveQuota = ConvertTo-Bytes $mbx.ArchiveQuota
                archiveWarningQuota = ConvertTo-Bytes $mbx.ArchiveWarningQuota
                litigationHoldEnabled = $mbx.LitigationHoldEnabled
                litigationHoldDate = if ($mbx.LitigationHoldDate) { $mbx.LitigationHoldDate.ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                litigationHoldOwner = $mbx.LitigationHoldOwner
                retentionPolicy = $mbx.RetentionPolicy
                retentionComment = $mbx.RetentionComment
                retentionUrl = $mbx.RetentionUrl
                forwardingAddress = $mbx.ForwardingAddress
                forwardingSmtpAddress = $mbx.ForwardingSmtpAddress
                deliverToMailboxAndForward = $mbx.DeliverToMailboxAndForward
                organizationalUnit = $mbx.OrganizationalUnit
                office = $mbx.Office
                department = $mbx.Department
                company = $mbx.Company
                customAttribute1 = $mbx.CustomAttribute1
                customAttribute2 = $mbx.CustomAttribute2
                customAttribute3 = $mbx.CustomAttribute3
                whenCreated = if ($mbx.WhenCreated) { $mbx.WhenCreated.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                whenChanged = if ($mbx.WhenChanged) { $mbx.WhenChanged.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                exchangeVersion = $mbx.ExchangeVersion
            }
            
            $mailboxes += $mailboxData
        }
        
        Write-Log "INFO" "Collected $($mailboxes.Count) mailboxes"
        return $mailboxes
        
    } catch {
        Write-Log "ERROR" "Failed to collect mailboxes: $_"
        throw
    }
}

function Get-ExchangeEmailAliases {
    param($Mailboxes, $Config)
    
    if (-not $Config.exchange.collectEmailAliases) {
        return @()
    }
    
    Write-Log "INFO" "Collecting email aliases..."
    
    $aliases = @()
    
    foreach ($mbx in $Mailboxes) {
        try {
            $mailbox = Get-Mailbox -Identity $mbx.guid -ErrorAction Stop
            
            foreach ($address in $mailbox.EmailAddresses) {
                $addressString = $address.ToString()
                $addressType = $addressString.Split(':')[0]
                $emailAddress = $addressString.Substring($addressString.IndexOf(':') + 1)
                
                $isPrimary = ($emailAddress -eq $mbx.primarySmtpAddress)
                
                $aliases += @{
                    mailboxGuid = $mbx.guid
                    emailAddress = $emailAddress
                    addressType = $addressType
                    isPrimary = $isPrimary
                }
            }
        } catch {
            Write-Log "WARN" "Failed to get aliases for $($mbx.primarySmtpAddress): $_"
        }
    }
    
    Write-Log "INFO" "Collected $($aliases.Count) email aliases"
    return $aliases
}

function Get-ExchangeMailboxPermissions {
    param($Mailboxes, $Config)
    
    if (-not $Config.exchange.collectPermissions) {
        return @()
    }
    
    Write-Log "INFO" "Collecting mailbox permissions..."
    
    $permissions = @()
    
    foreach ($mbx in $Mailboxes) {
        try {
            # Get FullAccess permissions
            $fullAccessPerms = Get-MailboxPermission -Identity $mbx.guid -ErrorAction SilentlyContinue | 
                Where-Object { $_.User -notlike "NT AUTHORITY\*" -and $_.User -notlike "S-1-*" -and $_.AccessRights -contains "FullAccess" }
            
            foreach ($perm in $fullAccessPerms) {
                $permissions += @{
                    mailboxGuid = $mbx.guid
                    permissionType = "FullAccess"
                    userIdentity = $perm.User
                    userSamAccountName = $perm.User.ToString().Split('\')[-1]
                    userDisplayName = $null
                    accessRights = @($perm.AccessRights)
                    inherited = $perm.IsInherited
                    deny = $perm.Deny
                    automapping = -not $perm.Deny
                }
            }
            
            # Get SendAs permissions
            $sendAsPerms = Get-ADPermission -Identity $mbx.distinguishedName -ErrorAction SilentlyContinue | 
                Where-Object { $_.ExtendedRights -like "*Send-As*" -and $_.User -notlike "NT AUTHORITY\*" -and $_.User -notlike "S-1-*" }
            
            foreach ($perm in $sendAsPerms) {
                $permissions += @{
                    mailboxGuid = $mbx.guid
                    permissionType = "SendAs"
                    userIdentity = $perm.User
                    userSamAccountName = $perm.User.ToString().Split('\')[-1]
                    userDisplayName = $null
                    accessRights = @("SendAs")
                    inherited = $perm.IsInherited
                    deny = $perm.Deny
                    automapping = $false
                }
            }
            
            # Get SendOnBehalf permissions
            $mailbox = Get-Mailbox -Identity $mbx.guid -ErrorAction Stop
            if ($mailbox.GrantSendOnBehalfTo) {
                foreach ($user in $mailbox.GrantSendOnBehalfTo) {
                    $permissions += @{
                        mailboxGuid = $mbx.guid
                        permissionType = "SendOnBehalf"
                        userIdentity = $user
                        userSamAccountName = $user.ToString().Split('\')[-1]
                        userDisplayName = $null
                        accessRights = @("SendOnBehalf")
                        inherited = $false
                        deny = $false
                        automapping = $false
                    }
                }
            }
        } catch {
            Write-Log "WARN" "Failed to get permissions for $($mbx.primarySmtpAddress): $_"
        }
    }
    
    Write-Log "INFO" "Collected $($permissions.Count) permission records"
    return $permissions
}

function Get-ExchangeMailboxStatistics {
    param($Mailboxes, $Config)
    
    if (-not $Config.exchange.collectStatistics) {
        return @()
    }
    
    Write-Log "INFO" "Collecting mailbox statistics..."
    
    $statistics = @()
    
    foreach ($mbx in $Mailboxes) {
        try {
            $stats = Get-MailboxStatistics -Identity $mbx.guid -ErrorAction Stop
            
            $statistics += @{
                mailboxGuid = $mbx.guid
                totalItemSize = ConvertTo-Bytes $stats.TotalItemSize
                totalDeletedItemSize = ConvertTo-Bytes $stats.TotalDeletedItemSize
                itemCount = $stats.ItemCount
                deletedItemCount = $stats.DeletedItemCount
                associatedItemCount = $stats.AssociatedItemCount
                folderCount = $stats.FolderCount
                database = $stats.Database
                databaseGuid = if ($stats.MailboxGuid) { $stats.MailboxGuid.ToString() } else { $null }
                isArchival = $stats.IsArchiveMailbox
                lastLogonTime = if ($stats.LastLogonTime) { $stats.LastLogonTime.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                lastLogoffTime = if ($stats.LastLogoffTime) { $stats.LastLogoffTime.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                lastLogonUserAccount = $stats.LastLogonUserAccount
                lastLoggedOnUserAccount = $stats.LastLoggedOnUserAccount
            }
            
            # Collect archive statistics if enabled
            if ($mbx.archiveEnabled -and $Config.exchange.includeArchivedMailboxes) {
                try {
                    $archiveStats = Get-MailboxStatistics -Identity $mbx.guid -Archive -ErrorAction Stop
                    
                    $statistics += @{
                        mailboxGuid = $mbx.guid
                        totalItemSize = ConvertTo-Bytes $archiveStats.TotalItemSize
                        totalDeletedItemSize = ConvertTo-Bytes $archiveStats.TotalDeletedItemSize
                        itemCount = $archiveStats.ItemCount
                        deletedItemCount = $archiveStats.DeletedItemCount
                        associatedItemCount = $archiveStats.AssociatedItemCount
                        folderCount = $archiveStats.FolderCount
                        database = $archiveStats.Database
                        databaseGuid = if ($archiveStats.MailboxGuid) { $archiveStats.MailboxGuid.ToString() } else { $null }
                        isArchival = $true
                        lastLogonTime = if ($archiveStats.LastLogonTime) { $archiveStats.LastLogonTime.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                        lastLogoffTime = if ($archiveStats.LastLogoffTime) { $archiveStats.LastLogoffTime.ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                        lastLogonUserAccount = $archiveStats.LastLogonUserAccount
                        lastLoggedOnUserAccount = $archiveStats.LastLoggedOnUserAccount
                    }
                } catch {
                    Write-Log "WARN" "Failed to get archive statistics for $($mbx.primarySmtpAddress): $_"
                }
            }
        } catch {
            Write-Log "WARN" "Failed to get statistics for $($mbx.primarySmtpAddress): $_"
        }
    }
    
    Write-Log "INFO" "Collected $($statistics.Count) statistics records"
    return $statistics
}

#endregion

# Main execution
try {
    # Load configuration
    Write-Host "Loading configuration from $ConfigPath..."
    $Config = Get-Content -Path $ConfigPath -Raw | ConvertFrom-Json
    
    Write-Log "INFO" "=== Exchange Sync Agent Started ==="
    Write-Log "INFO" "Version: 1.0.0"
    Write-Log "INFO" "Config: $ConfigPath"
    Write-Log "INFO" "Full Sync: $FullSync"
    Write-Log "INFO" "Dry Run: $DryRun"
    
    # Validate configuration
    Write-Log "INFO" "Validating configuration..."
    if (-not $Config.platform.apiUrl) {
        throw "API URL not configured"
    }
    
    # Auto-detect Exchange organization
    Write-Log "INFO" "Auto-detecting Exchange organization..."
    try {
        $ExchangeOrg = (Get-OrganizationConfig).Name
        $ExchangeVersion = (Get-ExchangeServer | Select-Object -First 1).AdminDisplayVersion
        Write-Log "INFO" "Detected organization: $ExchangeOrg"
        Write-Log "INFO" "Exchange version: $ExchangeVersion"
    } catch {
        Write-Log "ERROR" "Failed to auto-detect Exchange organization. Ensure this server has Exchange Management Shell loaded."
        throw "Unable to determine Exchange organization"
    }
    
    # Get API key from secure store or config
    $ApiKey = $null
    if ($Config.advanced.useSecureCredentialStore) {
        Write-Log "INFO" "Retrieving API key from Windows Credential Manager..."
        $ApiKey = Get-StoredCredential -Target "MSPReports-Exchange-Agent"
    }
    if (-not $ApiKey) {
        Write-Log "WARN" "Using API key from config file (not recommended for production)"
        $ApiKey = $Config.platform.apiKey
    }
    if (-not $ApiKey) {
        throw "API key not configured"
    }
    
    # Collect Exchange data
    $Mailboxes = Get-ExchangeMailboxes -Config $Config
    $EmailAliases = Get-ExchangeEmailAliases -Mailboxes $Mailboxes -Config $Config
    $Permissions = Get-ExchangeMailboxPermissions -Mailboxes $Mailboxes -Config $Config
    $Statistics = Get-ExchangeMailboxStatistics -Mailboxes $Mailboxes -Config $Config
    
    # Prepare payload
    Write-Log "INFO" "Preparing sync payload..."
    $Payload = @{
        syncType = if ($FullSync) { "full" } else { "delta" }
        syncTimestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
        exchangeOrganization = $ExchangeOrg
        exchangeVersion = $ExchangeVersion.ToString()
        data = @{
            mailboxes = $Mailboxes
            emailAliases = $EmailAliases
            permissions = $Permissions
            statistics = $Statistics
        }
        statistics = @{
            mailboxCount = $Mailboxes.Count
            aliasCount = $EmailAliases.Count
            permissionCount = $Permissions.Count
            statisticCount = $Statistics.Count
        }
    }
    
    # Convert to JSON
    $PayloadJson = $Payload | ConvertTo-Json -Depth 10 -Compress
    $PayloadSize = [System.Text.Encoding]::UTF8.GetByteCount($PayloadJson)
    Write-Log "INFO" "Payload size: $([Math]::Round($PayloadSize / 1024 / 1024, 2)) MB"
    
    if ($DryRun) {
        Write-Log "INFO" "DRY RUN - Not sending to API"
        Write-Log "INFO" "Payload preview: $($PayloadJson.Substring(0, [Math]::Min(500, $PayloadJson.Length)))..."
    } else {
        # Send to API
        Write-Log "INFO" "Sending data to platform API..."
        
        $headers = @{
            "Authorization" = "Bearer $ApiKey"
            "Content-Type" = "application/json"
        }
        
        $uri = "$($Config.platform.apiUrl)/integrations/exchange/push"
        
        try {
            $response = Invoke-RestMethod -Uri $uri -Method Post -Headers $headers -Body $PayloadJson -TimeoutSec $Config.platform.timeout
            
            Write-Log "INFO" "Sync successful!"
            Write-Log "INFO" "Response: $($response | ConvertTo-Json -Compress)"
            
        } catch {
            Write-Log "ERROR" "API request failed: $_"
            Write-Log "ERROR" "Status Code: $($_.Exception.Response.StatusCode.value__)"
            Write-Log "ERROR" "Status Description: $($_.Exception.Response.StatusDescription)"
            throw
        }
    }
    
    Write-Log "INFO" "=== Exchange Sync Agent Completed Successfully ==="
    exit 0
    
} catch {
    Write-Log "ERROR" "=== Exchange Sync Agent Failed ==="
    Write-Log "ERROR" "Error: $_"
    Write-Log "ERROR" "Stack Trace: $($_.ScriptStackTrace)"
    exit 1
}
