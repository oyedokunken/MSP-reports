# Active Directory Sync Agent

## Overview

PowerShell agent that runs on Windows Server to collect Active Directory data and push it to the MSP Reports Platform.

## Features

- **User Collection**: All AD users with detailed attributes
- **Security Groups**: Groups and their member mappings
- **License Usage**: Microsoft 365 license assignment tracking
- **Secure Push**: HTTPS with API key authentication
- **Delta Sync**: Only sends changes after initial sync
- **Retry Logic**: Automatic retry with exponential backoff
- **Logging**: Comprehensive logging to Event Viewer and file

## Requirements

- Windows Server 2012 R2 or later
- PowerShell 5.1 or later
- Active Directory PowerShell module
- Network access to MSP Reports Platform API
- Service account with AD read permissions

## Installation

### 1. Copy Files to Server

Copy the entire `active-directory` folder to the server:
```
C:\Program Files\MSPReports\Agents\ActiveDirectory\
```

### 2. Configure Agent

Copy and edit the configuration file:
```powershell
cd "C:\Program Files\MSPReports\Agents\ActiveDirectory"
Copy-Item config.example.json config.json
notepad config.json
```

Edit `config.json`:
```json
{
  "platform": {
    "apiUrl": "https://reports.yourmsp.com/api",
    "apiKey": "your-api-key-here",
    "companyId": 1
  },
  "sync": {
    "intervalMinutes": 240,
    "enableDeltaSync": true,
    "maxRetries": 3
  },
  "activeDirectory": {
    "domain": "yourdomain.local",
    "includeDisabledUsers": false,
    "includeDisabledComputers": false,
    "filterOUs": [],
    "excludeSystemGroups": true
  },
  "logging": {
    "level": "Info",
    "logPath": "C:\\Logs\\MSPReports\\AD",
    "maxLogSizeMB": 100,
    "retentionDays": 30
  }
}
```

### 3. Store API Key Securely (Optional but Recommended)

Store the API key in Windows Credential Manager instead of plaintext config:
```powershell
.\install.ps1 -StoreCredentials
# When prompted, enter your API key
```

### 4. Install as Scheduled Task

Run the installation script:
```powershell
.\install.ps1 -InstallScheduledTask
```

This creates a scheduled task that runs every 4 hours (configurable).

### 5. Or Install as Windows Service

For continuous operation:
```powershell
.\install.ps1 -InstallService
```

This requires NSSM (Non-Sucking Service Manager) which will be downloaded automatically.

## Manual Execution

Test the agent manually:
```powershell
.\ad-sync-agent.ps1 -ConfigPath ".\config.json"
```

Force a full sync (ignore delta):
```powershell
.\ad-sync-agent.ps1 -ConfigPath ".\config.json" -FullSync
```

Dry run (no API push):
```powershell
.\ad-sync-agent.ps1 -ConfigPath ".\config.json" -DryRun
```

## Uninstallation

Remove scheduled task:
```powershell
.\uninstall.ps1 -RemoveScheduledTask
```

Remove service:
```powershell
.\uninstall.ps1 -RemoveService
```

## Data Collected

### Users
- User Principal Name (UPN)
- Display Name
- Email Address
- Department
- Title
- Manager
- Enabled/Disabled status
- Last Logon timestamp
- Password Last Set
- Account Expiration
- Account Locked status
- Distinguished Name

### Security Groups
- Group Name
- Group Type (Security, Distribution)
- Group Scope (Global, Universal, Domain Local)
- Description
- Members (users mapped to groups)
- Distinguished Name

### License Usage
- Microsoft 365 licenses assigned
- License SKU
- Service plans enabled/disabled
- Assignment date

## Troubleshooting

### Check Agent Status

View recent logs:
```powershell
Get-Content "C:\Logs\MSPReports\AD\ad-sync-agent.log" -Tail 50
```

View Event Viewer logs:
```
Event Viewer > Applications and Services Logs > MSP Reports AD Agent
```

### Test API Connectivity

```powershell
Import-Module .\modules\APIClient.psm1
Test-APIConnection -ApiUrl "https://reports.yourmsp.com/api" -ApiKey "your-key"
```

### Test AD Connectivity

```powershell
Import-Module ActiveDirectory
Get-ADDomain
Get-ADUser -Filter * -ResultSetSize 1
```

### Common Issues

**Issue**: "ActiveDirectory module not found"
- **Solution**: Install RSAT tools: `Install-WindowsFeature RSAT-AD-PowerShell`

**Issue**: "Access denied" when querying AD
- **Solution**: Run as service account with AD read permissions

**Issue**: "API connection failed"
- **Solution**: Check network connectivity, firewall rules, API URL, and API key

**Issue**: "Scheduled task not running"
- **Solution**: Check task scheduler, verify service account has "Log on as batch job" right

## Security Considerations

1. **Service Account**: Run as dedicated service account with minimal permissions
2. **API Key**: Store in Windows Credential Manager, not plaintext config
3. **TLS**: Always use HTTPS for API communication
4. **Logging**: Ensure logs don't contain sensitive data
5. **Permissions**: Lock down agent directory to Administrators only

## Performance

- **Initial Sync**: 5-10 minutes for 1000 users
- **Delta Sync**: 30-60 seconds for typical changes
- **Memory Usage**: ~100MB during sync
- **Network**: ~1MB per 1000 users (compressed)

## Support

For issues or questions:
- Check logs first
- Contact support: support@athenanetworks.com.au
- Documentation: https://docs.athenanetworks.com.au/msp-reports/ad-agent
