<#
.SYNOPSIS
    MSP Reports - Active Directory Data Collection Module

.DESCRIPTION
    Functions to collect users, security groups, group memberships, and license usage from Active Directory
#>

# Import required modules
Import-Module ActiveDirectory -ErrorAction Stop

<#
.SYNOPSIS
    Collect Active Directory users

.PARAMETER Config
    Active Directory configuration object

.PARAMETER LastSyncTime
    Last successful sync time for delta sync
#>
function Get-ADUsersData {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [PSCustomObject]$Config,
        
        [Parameter(Mandatory=$false)]
        [DateTime]$LastSyncTime
    )
    
    try {
        Write-Verbose "Collecting AD users..."
        
        # Build filter
        $Filter = "*"
        if (-not $Config.includeDisabledUsers) {
            $Filter = "Enabled -eq `$true"
        }
        
        # Properties to collect
        $Properties = @(
            'UserPrincipalName',
            'SamAccountName',
            'DisplayName',
            'GivenName',
            'Surname',
            'EmailAddress',
            'Department',
            'Title',
            'Manager',
            'Enabled',
            'LastLogonDate',
            'PasswordLastSet',
            'PasswordNeverExpires',
            'AccountExpirationDate',
            'LockedOut',
            'Created',
            'Modified',
            'DistinguishedName',
            'Description',
            'Office',
            'OfficePhone',
            'MobilePhone',
            'Company'
        )
        
        # Get users
        $AllUsers = Get-ADUser -Filter $Filter -Properties $Properties
        
        # Filter by OU if specified
        if ($Config.filterOUs -and $Config.filterOUs.Count -gt 0) {
            $AllUsers = $AllUsers | Where-Object {
                $userDN = $_.DistinguishedName
                $Config.filterOUs | ForEach-Object {
                    if ($userDN -like "*$_*") { return $true }
                }
                return $false
            }
        }
        
        # Exclude OUs if specified
        if ($Config.excludeOUs -and $Config.excludeOUs.Count -gt 0) {
            $AllUsers = $AllUsers | Where-Object {
                $userDN = $_.DistinguishedName
                $excluded = $false
                $Config.excludeOUs | ForEach-Object {
                    if ($userDN -like "*$_*") { $excluded = $true }
                }
                return -not $excluded
            }
        }
        
        # Filter by last modified if delta sync
        if ($LastSyncTime) {
            $AllUsers = $AllUsers | Where-Object { 
                $_.Modified -gt $LastSyncTime -or $_.Created -gt $LastSyncTime 
            }
        }
        
        # Apply max users limit
        if ($Config.maxUsersPerSync -gt 0 -and $AllUsers.Count -gt $Config.maxUsersPerSync) {
            Write-Warning "User count ($($AllUsers.Count)) exceeds limit ($($Config.maxUsersPerSync)). Trimming results."
            $AllUsers = $AllUsers | Select-Object -First $Config.maxUsersPerSync
        }
        
        # Transform to standardized format
        $Users = $AllUsers | ForEach-Object {
            $managerName = $null
            if ($_.Manager) {
                try {
                    $managerObj = Get-ADUser $_.Manager -Properties DisplayName -ErrorAction SilentlyContinue
                    $managerName = $managerObj.DisplayName
                } catch {
                    # Manager lookup failed, skip
                }
            }
            
            @{
                userPrincipalName = $_.UserPrincipalName
                samAccountName = $_.SamAccountName
                displayName = $_.DisplayName
                givenName = $_.GivenName
                surname = $_.Surname
                email = $_.EmailAddress
                department = $_.Department
                title = $_.Title
                manager = $managerName
                managerDN = $_.Manager
                enabled = $_.Enabled
                lastLogon = if ($_.LastLogonDate) { $_.LastLogonDate.ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                passwordLastSet = if ($_.PasswordLastSet) { $_.PasswordLastSet.ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                passwordNeverExpires = $_.PasswordNeverExpires
                accountExpiration = if ($_.AccountExpirationDate) { $_.AccountExpirationDate.ToString("yyyy-MM-ddTHH:mm:ssZ") } else { $null }
                lockedOut = $_.LockedOut
                created = $_.Created.ToString("yyyy-MM-ddTHH:mm:ssZ")
                modified = $_.Modified.ToString("yyyy-MM-ddTHH:mm:ssZ")
                distinguishedName = $_.DistinguishedName
                description = $_.Description
                office = $_.Office
                officePhone = $_.OfficePhone
                mobilePhone = $_.MobilePhone
                company = $_.Company
            }
        }
        
        return $Users
        
    } catch {
        Write-Error "Failed to collect AD users: $_"
        throw
    }
}

<#
.SYNOPSIS
    Collect Active Directory security groups

.PARAMETER Config
    Active Directory configuration object

.PARAMETER LastSyncTime
    Last successful sync time for delta sync
#>
function Get-ADSecurityGroups {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [PSCustomObject]$Config,
        
        [Parameter(Mandatory=$false)]
        [DateTime]$LastSyncTime
    )
    
    try {
        Write-Verbose "Collecting AD security groups..."
        
        # Get all security groups
        $Filter = "GroupCategory -eq 'Security'"
        
        $Properties = @(
            'Name',
            'SamAccountName',
            'GroupCategory',
            'GroupScope',
            'Description',
            'Created',
            'Modified',
            'DistinguishedName',
            'ManagedBy'
        )
        
        $AllGroups = Get-ADGroup -Filter $Filter -Properties $Properties
        
        # Exclude system groups if configured
        if ($Config.excludeSystemGroups) {
            $SystemGroups = @(
                'Domain Admins',
                'Domain Users',
                'Domain Guests',
                'Domain Computers',
                'Domain Controllers',
                'Schema Admins',
                'Enterprise Admins',
                'Cert Publishers',
                'Group Policy Creator Owners',
                'RAS and IAS Servers',
                'Allowed RODC Password Replication Group',
                'Denied RODC Password Replication Group',
                'Read-only Domain Controllers',
                'Enterprise Read-only Domain Controllers',
                'Cloneable Domain Controllers',
                'Protected Users',
                'Key Admins',
                'Enterprise Key Admins',
                'DnsAdmins',
                'DnsUpdateProxy'
            )
            
            $AllGroups = $AllGroups | Where-Object { 
                $SystemGroups -notcontains $_.Name 
            }
        }
        
        # Filter by last modified if delta sync
        if ($LastSyncTime) {
            $AllGroups = $AllGroups | Where-Object { 
                $_.Modified -gt $LastSyncTime -or $_.Created -gt $LastSyncTime 
            }
        }
        
        # Apply max groups limit
        if ($Config.maxGroupsPerSync -gt 0 -and $AllGroups.Count -gt $Config.maxGroupsPerSync) {
            Write-Warning "Group count ($($AllGroups.Count)) exceeds limit ($($Config.maxGroupsPerSync)). Trimming results."
            $AllGroups = $AllGroups | Select-Object -First $Config.maxGroupsPerSync
        }
        
        # Transform to standardized format
        $Groups = $AllGroups | ForEach-Object {
            $managedByName = $null
            if ($_.ManagedBy) {
                try {
                    $managedByObj = Get-ADObject $_.ManagedBy -Properties DisplayName -ErrorAction SilentlyContinue
                    $managedByName = $managedByObj.Name
                } catch {
                    # Managed by lookup failed, skip
                }
            }
            
            @{
                name = $_.Name
                samAccountName = $_.SamAccountName
                groupCategory = $_.GroupCategory.ToString()
                groupScope = $_.GroupScope.ToString()
                description = $_.Description
                created = $_.Created.ToString("yyyy-MM-ddTHH:mm:ssZ")
                modified = $_.Modified.ToString("yyyy-MM-ddTHH:mm:ssZ")
                distinguishedName = $_.DistinguishedName
                managedBy = $managedByName
                managedByDN = $_.ManagedBy
            }
        }
        
        return $Groups
        
    } catch {
        Write-Error "Failed to collect AD security groups: $_"
        throw
    }
}

<#
.SYNOPSIS
    Collect group membership mappings

.PARAMETER Groups
    Array of groups to collect memberships for

.PARAMETER Config
    Active Directory configuration object
#>
function Get-ADGroupMemberships {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [Array]$Groups,
        
        [Parameter(Mandatory=$true)]
        [PSCustomObject]$Config
    )
    
    try {
        Write-Verbose "Collecting group memberships..."
        
        $Memberships = @()
        
        foreach ($Group in $Groups) {
            try {
                # Get group members (users only, not nested groups)
                $Members = Get-ADGroupMember -Identity $Group.distinguishedName -ErrorAction SilentlyContinue | 
                    Where-Object { $_.objectClass -eq 'user' }
                
                foreach ($Member in $Members) {
                    $Memberships += @{
                        groupName = $Group.name
                        groupDN = $Group.distinguishedName
                        userSamAccountName = $Member.SamAccountName
                        userDN = $Member.distinguishedName
                        membershipType = 'Direct'
                    }
                }
                
            } catch {
                Write-Warning "Failed to get members for group '$($Group.name)': $_"
                continue
            }
        }
        
        return $Memberships
        
    } catch {
        Write-Error "Failed to collect group memberships: $_"
        throw
    }
}


<#
.SYNOPSIS
    Get sync state from file

.PARAMETER ConfigPath
    Path to configuration file (used to determine state file location)
#>
function Get-SyncState {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$ConfigPath
    )
    
    $StateFile = [System.IO.Path]::ChangeExtension($ConfigPath, "state.json")
    
    if (Test-Path $StateFile) {
        $State = Get-Content $StateFile -Raw | ConvertFrom-Json
        return $State
    }
    
    return @{
        LastSuccessfulSync = $null
        LastAttempt = $null
        ConsecutiveFailures = 0
    }
}

<#
.SYNOPSIS
    Save sync state to file

.PARAMETER ConfigPath
    Path to configuration file (used to determine state file location)

.PARAMETER Success
    Whether the sync was successful

.PARAMETER Message
    Status message
#>
function Save-SyncState {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$ConfigPath,
        
        [Parameter(Mandatory=$true)]
        [bool]$Success,
        
        [Parameter(Mandatory=$false)]
        [string]$Message
    )
    
    $StateFile = [System.IO.Path]::ChangeExtension($ConfigPath, "state.json")
    
    $CurrentState = Get-SyncState -ConfigPath $ConfigPath
    
    $NewState = @{
        LastAttempt = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
        LastMessage = $Message
    }
    
    if ($Success) {
        $NewState.LastSuccessfulSync = $NewState.LastAttempt
        $NewState.ConsecutiveFailures = 0
    } else {
        $NewState.LastSuccessfulSync = $CurrentState.LastSuccessfulSync
        $NewState.ConsecutiveFailures = $CurrentState.ConsecutiveFailures + 1
    }
    
    $NewState | ConvertTo-Json | Set-Content $StateFile -Force
}

# Export functions
Export-ModuleMember -Function @(
    'Get-ADUsersData',
    'Get-ADSecurityGroups',
    'Get-ADGroupMemberships',
    'Get-SyncState',
    'Save-SyncState'
)
