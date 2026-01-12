# Microsoft Exchange Server Sync Agent

Syncs Exchange mailbox data to MSP Reports Platform for license tracking, capacity planning, and compliance reporting.

## Features

- **Mailbox Inventory:** User, shared, and resource mailboxes
- **Email Aliases:** All SMTP and non-SMTP addresses per mailbox
- **Permissions Tracking:** FullAccess, SendAs, and SendOnBehalf permissions
- **Usage Statistics:** Mailbox sizes, item counts, last logon times
- **Archive Support:** Archive mailbox tracking and statistics
- **Litigation Hold:** Compliance tracking
- **Quota Monitoring:** Warn before mailboxes hit limits
- **AD Integration:** Links mailboxes to Active Directory users (via samAccountName)

## Requirements

- **Windows Server** with Exchange Server installed
- **Exchange Management Shell** loaded
- **PowerShell 5.1+**
- **Network access** to MSP Reports Platform API
- **Customer-scoped API Key** from platform

## Installation

### Step 1: Copy Files

Copy the entire `exchange` folder to your Exchange server:
```
C:\MSPReports\Exchange\
```

### Step 2: Configure

Copy `config.example.json` to `config.json`:
```powershell
Copy-Item config.example.json config.json
```

Edit `config.json`:
```json
{
  "platform": {
    "apiUrl": "https://reports.yourmsp.com/api",
    "apiKey": ""
  }
}
```

**Note:** The API key will be provided when you create a customer-scoped API key in the platform:
```bash
php artisan apikey:create --customer-id=5 --name="Customer Exchange Agent"
```

### Step 3: Store API Key Securely

```powershell
.\install.ps1 -StoreCredentials
```

This stores the API key in Windows Credential Manager instead of the config file.

### Step 4: Test Configuration

```powershell
.\install.ps1 -TestConfiguration
```

Run a dry-run sync to verify everything works:
```powershell
.\exchange-sync-agent.ps1 -DryRun
```

### Step 5: Install as Scheduled Task

```powershell
.\install.ps1 -InstallScheduledTask
```

This creates a scheduled task that runs every 4 hours (configurable in config.json).

## Usage

### Manual Sync

From Exchange Management Shell:

```powershell
# Full sync
.\exchange-sync-agent.ps1

# Dry run (test without sending)
.\exchange-sync-agent.ps1 -DryRun

# Force full sync
.\exchange-sync-agent.ps1 -FullSync
```

### Scheduled Task

```powershell
# Start immediately
Start-ScheduledTask -TaskName "MSPReports-Exchange-Sync"

# Check status
Get-ScheduledTask -TaskName "MSPReports-Exchange-Sync" | Get-ScheduledTaskInfo

# View logs
Get-Content "C:\Logs\MSPReports\Exchange\exchange-sync-$(Get-Date -Format 'yyyyMMdd').log"
```

## Configuration Options

### Platform Settings

```json
{
  "platform": {
    "apiUrl": "https://reports.yourmsp.com/api",
    "apiKey": "",
    "timeout": 300
  }
}
```

- **apiUrl:** Platform API endpoint (auto-detected domain/customer context from API key)
- **apiKey:** Customer-scoped API key (or leave empty to use Credential Manager)
- **timeout:** API request timeout in seconds

### Sync Settings

```json
{
  "sync": {
    "intervalMinutes": 240,
    "enableDeltaSync": false,
    "maxRetries": 3,
    "retryDelaySeconds": 30,
    "compressionEnabled": true
  }
}
```

- **intervalMinutes:** How often to sync (default: 4 hours)
- **enableDeltaSync:** Not yet implemented (always full sync)
- **maxRetries:** Retry failed API requests
- **compressionEnabled:** Compress JSON payload

### Exchange Settings

```json
{
  "exchange": {
    "includeSharedMailboxes": true,
    "includeResourceMailboxes": true,
    "includeArchivedMailboxes": true,
    "collectPermissions": true,
    "collectStatistics": true,
    "collectEmailAliases": true,
    "maxMailboxesPerSync": 5000,
    "filterDatabases": [],
    "excludeDatabases": []
  }
}
```

- **includeSharedMailboxes:** Include shared mailboxes in sync
- **includeResourceMailboxes:** Include room/equipment mailboxes
- **includeArchivedMailboxes:** Collect archive mailbox statistics
- **collectPermissions:** Sync FullAccess/SendAs/SendOnBehalf permissions
- **collectStatistics:** Sync mailbox sizes and usage stats
- **collectEmailAliases:** Sync all email addresses per mailbox
- **maxMailboxesPerSync:** Limit (for large environments)
- **filterDatabases:** Only sync mailboxes in these databases (empty = all)
- **excludeDatabases:** Exclude mailboxes in these databases

### Logging Settings

```json
{
  "logging": {
    "level": "Info",
    "logPath": "C:\\Logs\\MSPReports\\Exchange",
    "maxLogSizeMB": 100,
    "retentionDays": 30,
    "enableEventLog": true,
    "enableConsole": true
  }
}
```

## Data Collected

### Mailbox Properties

- **Identity:** Guid, display name, alias, UPN, samAccountName
- **Type:** User, Shared, Room, Equipment
- **Email Addresses:** Primary SMTP and all aliases
- **Database:** Database name and server
- **Quotas:** ProhibitSend, ProhibitSendReceive, IssueWarning
- **Current Size:** Total and deleted item sizes
- **Archive:** Archive enabled, size, quotas
- **Compliance:** Litigation hold status
- **Forwarding:** Forwarding rules
- **Organizational:** OU, office, department, company
- **Timestamps:** Created, modified, last logon

### Email Aliases

- Email address
- Address type (SMTP, X400, etc.)
- Primary flag

### Permissions

- **FullAccess:** Users with full mailbox access
- **SendAs:** Users who can send as the mailbox
- **SendOnBehalf:** Users who can send on behalf
- Inherited/explicit flag
- Automapping enabled

### Statistics

- Total item size and count
- Deleted item size and count
- Associated items (calendar, contacts)
- Folder count
- Last logon time
- Database information

## Troubleshooting

### Exchange Management Shell Not Loaded

Error: `Get-Mailbox : The term 'Get-Mailbox' is not recognized`

**Solution:** Run the script from Exchange Management Shell or add Exchange snap-in:
```powershell
Add-PSSnapin Microsoft.Exchange.Management.PowerShell.SnapIn
```

### Permission Denied

Error: `You don't have sufficient permissions`

**Solution:** Run as user with Exchange Organization Management or View-Only Organization Management role.

### API Connection Failed

Error: `API request failed: Unable to connect to the remote server`

**Solution:**
1. Check `apiUrl` in config.json
2. Verify network connectivity: `Test-NetConnection reports.yourmsp.com -Port 443`
3. Check firewall rules
4. Verify API key is valid

### Large Environment Performance

For environments with 1000+ mailboxes:

1. Increase `maxMailboxesPerSync` if needed
2. Use `filterDatabases` to sync specific databases only
3. Run during off-peak hours
4. Consider multiple agents per database

## Security

### API Key Storage

**Recommended:** Use Windows Credential Manager
```powershell
.\install.ps1 -StoreCredentials
```

**Not Recommended:** Store in config.json (only for testing)

### Permissions Required

The agent needs:
- **Exchange:** View-Only Organization Management (read-only)
- **No write permissions required**
- **No user creation or modification**

### Network Security

- **TLS 1.2+** for API communication
- **API key** required for authentication
- **Customer-scoped** - can only push data for assigned customer

## Reporting Capabilities

Once synced, the platform provides:

- **License Optimization:** Identify unused/underutilized mailboxes
- **Capacity Planning:** Track growth trends, predict storage needs
- **Quota Alerts:** Mailboxes near or over quota
- **Permission Audits:** Who has access to what
- **Compliance Reports:** Litigation hold, archive status
- **Shared Mailbox Inventory:** All shared mailboxes and delegates
- **Resource Booking:** Room and equipment mailboxes
- **Alias Management:** All email addresses in use
- **Inactive Mailbox Detection:** Users who haven't logged in recently

## Support

For issues or questions:
- Check logs: `C:\Logs\MSPReports\Exchange\`
- Review platform documentation
- Contact MSP support

## Version

**Version:** 1.0.0
**Last Updated:** January 2026
