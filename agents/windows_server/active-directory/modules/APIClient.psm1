<#
.SYNOPSIS
    API Client Module for MSP Reports Platform

.DESCRIPTION
    Functions to communicate with the MSP Reports Platform API
#>

<#
.SYNOPSIS
    Test API connectivity

.PARAMETER ApiUrl
    Base API URL

.PARAMETER ApiKey
    API authentication key
#>
function Test-APIConnection {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$ApiUrl,
        
        [Parameter(Mandatory=$true)]
        [string]$ApiKey
    )
    
    try {
        $Headers = @{
            'Authorization' = "Bearer $ApiKey"
            'Content-Type' = 'application/json'
            'User-Agent' = 'MSPReports-AD-Agent/1.0'
        }
        
        $HealthUrl = "$ApiUrl/health"
        
        $Response = Invoke-RestMethod -Uri $HealthUrl -Method Get -Headers $Headers -TimeoutSec 30 -ErrorAction Stop
        
        return @{
            Success = $true
            Message = "Connection successful"
            Response = $Response
        }
        
    } catch {
        return @{
            Success = $false
            Error = $_.Exception.Message
        }
    }
}

<#
.SYNOPSIS
    Push Active Directory data to API

.PARAMETER ApiUrl
    Base API URL

.PARAMETER ApiKey
    API authentication key

.PARAMETER Payload
    Data payload to push

.PARAMETER MaxRetries
    Maximum number of retry attempts

.PARAMETER RetryDelay
    Delay between retries in seconds
#>
function Push-ADDataToAPI {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$ApiUrl,
        
        [Parameter(Mandatory=$true)]
        [string]$ApiKey,
        
        [Parameter(Mandatory=$true)]
        [hashtable]$Payload,
        
        [Parameter(Mandatory=$false)]
        [int]$MaxRetries = 3,
        
        [Parameter(Mandatory=$false)]
        [int]$RetryDelay = 30
    )
    
    $Endpoint = "$ApiUrl/integrations/active-directory/push"
    
    $Headers = @{
        'Authorization' = "Bearer $ApiKey"
        'Content-Type' = 'application/json'
        'User-Agent' = 'MSPReports-AD-Agent/1.0'
        'Accept' = 'application/json'
    }
    
    $Body = $Payload | ConvertTo-Json -Depth 10 -Compress
    
    $Attempt = 0
    $Success = $false
    $LastError = $null
    
    while ($Attempt -lt $MaxRetries -and -not $Success) {
        $Attempt++
        
        try {
            Write-Verbose "Attempt $Attempt of $MaxRetries to push data to API..."
            
            $Response = Invoke-RestMethod `
                -Uri $Endpoint `
                -Method Post `
                -Headers $Headers `
                -Body $Body `
                -TimeoutSec 300 `
                -ErrorAction Stop
            
            $Success = $true
            
            return @{
                Success = $true
                Message = "Data pushed successfully"
                Response = $Response
            }
            
        } catch {
            $LastError = $_.Exception.Message
            $StatusCode = $_.Exception.Response.StatusCode.value__
            
            Write-Warning "Attempt $Attempt failed: $LastError (Status: $StatusCode)"
            
            # Don't retry on client errors (4xx)
            if ($StatusCode -ge 400 -and $StatusCode -lt 500) {
                Write-Warning "Client error detected. Not retrying."
                break
            }
            
            # Wait before retry (exponential backoff)
            if ($Attempt -lt $MaxRetries) {
                $WaitTime = $RetryDelay * [Math]::Pow(2, $Attempt - 1)
                Write-Verbose "Waiting $WaitTime seconds before retry..."
                Start-Sleep -Seconds $WaitTime
            }
        }
    }
    
    # All retries failed
    return @{
        Success = $false
        Error = "Failed after $Attempt attempts. Last error: $LastError"
    }
}

<#
.SYNOPSIS
    Get stored credential from Windows Credential Manager

.PARAMETER Target
    Credential target name
#>
function Get-StoredCredential {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$Target
    )
    
    try {
        # Try to use CredentialManager module if available
        if (Get-Module -ListAvailable -Name CredentialManager) {
            Import-Module CredentialManager -ErrorAction Stop
            $Cred = Get-StoredCredential -Target $Target -ErrorAction SilentlyContinue
            if ($Cred) {
                return $Cred.GetNetworkCredential().Password
            }
        }
        
        # Fallback: Use cmdkey utility
        $cmdkeyOutput = cmdkey /list:$Target 2>&1
        if ($LASTEXITCODE -eq 0) {
            # Credential exists but we can't retrieve password with cmdkey
            # This is a limitation - recommend using CredentialManager module
            Write-Warning "Credential found but cannot be retrieved without CredentialManager module"
            return $null
        }
        
        return $null
        
    } catch {
        Write-Verbose "Failed to get stored credential: $_"
        return $null
    }
}

<#
.SYNOPSIS
    Store credential in Windows Credential Manager

.PARAMETER Target
    Credential target name

.PARAMETER Username
    Username (can be dummy for API keys)

.PARAMETER Password
    Password or API key
#>
function Set-StoredCredential {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$Target,
        
        [Parameter(Mandatory=$false)]
        [string]$Username = "apikey",
        
        [Parameter(Mandatory=$true)]
        [string]$Password
    )
    
    try {
        # Try to use CredentialManager module if available
        if (Get-Module -ListAvailable -Name CredentialManager) {
            Import-Module CredentialManager -ErrorAction Stop
            
            $SecurePassword = ConvertTo-SecureString $Password -AsPlainText -Force
            $Credential = New-Object System.Management.Automation.PSCredential($Username, $SecurePassword)
            
            New-StoredCredential -Target $Target -Credentials $Credential -Type Generic -Persist LocalMachine | Out-Null
            
            return $true
        }
        
        # Fallback: Use cmdkey utility
        $process = Start-Process -FilePath "cmdkey.exe" -ArgumentList "/generic:$Target /user:$Username /pass:$Password" -Wait -NoNewWindow -PassThru
        
        return ($process.ExitCode -eq 0)
        
    } catch {
        Write-Error "Failed to store credential: $_"
        return $false
    }
}

<#
.SYNOPSIS
    Remove stored credential from Windows Credential Manager

.PARAMETER Target
    Credential target name
#>
function Remove-StoredCredential {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$Target
    )
    
    try {
        # Try to use CredentialManager module if available
        if (Get-Module -ListAvailable -Name CredentialManager) {
            Import-Module CredentialManager -ErrorAction Stop
            Remove-StoredCredential -Target $Target -ErrorAction SilentlyContinue | Out-Null
            return $true
        }
        
        # Fallback: Use cmdkey utility
        $process = Start-Process -FilePath "cmdkey.exe" -ArgumentList "/delete:$Target" -Wait -NoNewWindow -PassThru
        
        return ($process.ExitCode -eq 0)
        
    } catch {
        Write-Error "Failed to remove credential: $_"
        return $false
    }
}

# Export functions
Export-ModuleMember -Function @(
    'Test-APIConnection',
    'Push-ADDataToAPI',
    'Get-StoredCredential',
    'Set-StoredCredential',
    'Remove-StoredCredential'
)
