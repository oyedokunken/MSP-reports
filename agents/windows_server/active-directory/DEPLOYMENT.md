# Active Directory Agent - Deployment Guide

## Pre-Deployment Checklist

### Server Requirements
- [ ] Windows Server 2012 R2 or later
- [ ] PowerShell 5.1 or later installed
- [ ] Active Directory PowerShell module (RSAT-AD-PowerShell)
- [ ] Network connectivity to MSP Reports Platform API
- [ ] Minimum 2GB free disk space for logs

### Account Requirements
- [ ] Service account created with:
  - [ ] Read permissions on Active Directory
  - [ ] "Log on as a batch job" right (for scheduled tasks)
  - [ ] "Log on as a service" right (for Windows service)
- [ ] Service account does NOT need Domain Admin rights
- [ ] Service account password documented securely

### Platform Preparation
- [ ] MSP Reports Platform API accessible
- [ ] Company created in platform
- [ ] API key generated for the agent
- [ ] API endpoint `/api/integrations/active-directory/push` implemented

## Deployment Steps

### Step 1: Copy Agent Files

1. Create directory on target server:
   ```powershell
   New-Item -ItemType Directory -Path "C:\Program Files\MSPReports\Agents\ActiveDirectory" -Force
   ```

2. Copy all agent files to the directory:
   - `ad-sync-agent.ps1`
   - `install.ps1`
   - `uninstall.ps1`
   - `test-agent.ps1`
   - `config.example.json`
   - `modules\` folder with all .psm1 files

### Step 2: Configure Agent

1. Copy example config:
   ```powershell
   cd "C:\Program Files\MSPReports\Agents\ActiveDirectory"
   Copy-Item config.example.json config.json
   ```

2. Edit `config.json`:
   ```json
   {
     "platform": {
       "apiUrl": "https://reports.customermsp.com/api",
       "apiKey": "your-api-key-here",
       "companyId": 1,
       "integrationId": null
     },
     "activeDirectory": {
       "domain": "customer.local",
       "includeDisabledUsers": false,
       "excludeSystemGroups": true
     },
     "logging": {
       "logPath": "C:\\Logs\\MSPReports\\AD"
     }
   }
   ```

3. Secure the API key (recommended):
   ```powershell
   .\install.ps1 -StoreCredentials
   # Enter API key when prompted
   
   # Remove from config.json
   (Get-Content config.json) -replace '"apiKey": ".+"', '"apiKey": ""' | Set-Content config.json
   ```

### Step 3: Test Components

1. Test Active Directory access:
   ```powershell
   .\test-agent.ps1 -TestADConnection
   ```

2. Test API connectivity:
   ```powershell
   .\test-agent.ps1 -TestAPIConnection
   ```

3. Test data collection (dry run):
   ```powershell
   .\test-agent.ps1 -TestDataCollection -ShowSample
   ```

4. Full dry run:
   ```powershell
   .\ad-sync-agent.ps1 -ConfigPath ".\config.json" -DryRun
   ```

### Step 4: Install Service

**Option A: Scheduled Task (Recommended)**
```powershell
.\install.ps1 -InstallScheduledTask -ServiceAccount "DOMAIN\svc-mspreports"
```

**Option B: Windows Service**
```powershell
.\install.ps1 -InstallService -ServiceAccount "DOMAIN\svc-mspreports"
```

### Step 5: Verify Installation

1. Check scheduled task:
   ```powershell
   Get-ScheduledTask -TaskName "MSP Reports - AD Sync"
   Get-ScheduledTaskInfo -TaskName "MSP Reports - AD Sync"
   ```

2. Check service (if installed as service):
   ```powershell
   Get-Service -Name "MSPReportsADSync"
   ```

3. Manually trigger sync:
   ```powershell
   Start-ScheduledTask -TaskName "MSP Reports - AD Sync"
   ```

4. Monitor logs:
   ```powershell
   Get-Content "C:\Logs\MSPReports\AD\ad-sync-agent-*.log" -Tail 50 -Wait
   ```

5. Verify data in platform:
   - Log into MSP Reports Platform
   - Navigate to Integrations → Active Directory
   - Verify users and groups synced

## Post-Deployment

### Monitoring

1. **Check sync status regularly:**
   ```powershell
   Get-Content "C:\Program Files\MSPReports\Agents\ActiveDirectory\config.state.json"
   ```

2. **Review logs for errors:**
   ```powershell
   Get-Content "C:\Logs\MSPReports\AD\*.log" | Select-String "ERROR"
   ```

3. **Monitor Event Viewer:**
   - Event Viewer → Applications and Services Logs → Application
   - Filter by source: "MSP Reports AD Agent"

### Maintenance

1. **Update agent** (when new version available):
   ```powershell
   # Stop service/task
   Stop-ScheduledTask -TaskName "MSP Reports - AD Sync"
   
   # Replace agent files
   # Copy new files over existing ones
   
   # Restart
   Start-ScheduledTask -TaskName "MSP Reports - AD Sync"
   ```

2. **Rotate API key:**
   ```powershell
   # Store new key
   .\install.ps1 -StoreCredentials
   
   # Or update config.json
   ```

3. **Review and adjust schedule:**
   ```powershell
   # Edit config.json
   # Change sync.intervalMinutes
   
   # Reinstall task
   .\uninstall.ps1 -RemoveScheduledTask
   .\install.ps1 -InstallScheduledTask
   ```

## Troubleshooting

### Agent won't start

**Check PowerShell execution policy:**
```powershell
Get-ExecutionPolicy
Set-ExecutionPolicy RemoteSigned -Scope LocalMachine
```

**Check service account permissions:**
```powershell
# Verify account can read AD
Get-ADUser -Filter * -Credential (Get-Credential)

# Check "Log on as batch job" right
secpol.msc → Local Policies → User Rights Assignment
```

### Sync failing

**Check API connectivity:**
```powershell
Test-NetConnection reports.customermsp.com -Port 443
```

**Verify API key:**
```powershell
.\test-agent.ps1 -TestAPIConnection
```

**Review detailed logs:**
```powershell
Get-Content "C:\Logs\MSPReports\AD\ad-sync-agent-*.log" -Tail 200
```

### Missing data

**Check filters in config.json:**
- `includeDisabledUsers`: Set to `true` to include disabled accounts
- `filterOUs`: Remove or adjust OU filters
- `excludeSystemGroups`: Set to `false` to include system groups

**Force full sync:**
```powershell
.\ad-sync-agent.ps1 -ConfigPath ".\config.json" -FullSync
```

### Performance issues

**Reduce batch size:**
```json
{
  "advanced": {
    "batchSize": 250,
    "throttleDelayMs": 100
  }
}
```

**Limit data collection:**
```json
{
  "activeDirectory": {
    "maxUsersPerSync": 5000,
    "maxGroupsPerSync": 2500
  }
}
```

## Security Recommendations

1. **Use least privilege service account**
   - Only grant "Read" permissions on AD
   - Do NOT use Domain Admin account

2. **Secure API key storage**
   - Use Windows Credential Manager (not config file)
   - Rotate keys every 90 days

3. **Protect agent directory**
   ```powershell
   icacls "C:\Program Files\MSPReports" /inheritance:r
   icacls "C:\Program Files\MSPReports" /grant "SYSTEM:(OI)(CI)F"
   icacls "C:\Program Files\MSPReports" /grant "Administrators:(OI)(CI)F"
   ```

4. **Enable log encryption** (if storing sensitive data)

5. **Use TLS 1.3** for API communication (configured by default)

6. **Review audit logs** for unauthorized access attempts

## Uninstallation

```powershell
cd "C:\Program Files\MSPReports\Agents\ActiveDirectory"

# Remove scheduled task
.\uninstall.ps1 -RemoveScheduledTask

# Or remove service
.\uninstall.ps1 -RemoveService

# Optionally remove credentials and logs
.\uninstall.ps1 -RemoveCredentials -RemoveLogs

# Manually delete agent directory if desired
Remove-Item "C:\Program Files\MSPReports\Agents\ActiveDirectory" -Recurse -Force
```

## Support

For issues or questions:
- Email: support@athenanetworks.com.au
- Documentation: https://docs.athenanetworks.com.au/msp-reports/ad-agent
- Create ticket in MSP Reports Platform
