# Integrations Page - User Guide

## Overview
The Integrations page allows you to connect external systems (PSA, RMM, security tools) to the reporting platform. Each integration automatically syncs customer data and historical information for report generation.

---

## Page Layout

### Header Section
- **Title**: "Integrations" with integration icon
- **Add Integration Button**: Create new integration connections
- **Search Bar**: Filter integrations by name or type
- **Status Filter**: Filter by Active, Disconnected, Error, or Importing Historical

### Integration Cards Grid
Each integration displays:
- **Integration Type Icon**: Visual identifier (Datto, Autotask, ITGlue, etc.)
- **Display Name**: Custom name for this integration
- **Status Badge**: 
  - 🟢 Active (green)
  - 🔴 Disconnected (red)
  - 🟡 Error (yellow)
  - 🔵 Importing Historical (blue)
- **Last Sync Time**: When data was last synchronized
- **Customer Count**: Number of customers from this integration
- **Master Customer List Badge**: Shows if this is the source of truth

---

## Features & How to Use

### 1. Add New Integration

**Steps:**
1. Click **"Add Integration"** button (top right)
2. Select integration type from dropdown:
   - Datto RMM
   - Autotask PSA
   - ITGlue
   - Sendmarc
   - SaaS Alerts
   - BitDefender
   - LibreNMS
   - Barracuda
3. Enter **Display Name** (e.g., "Production Datto")
4. Configure **Credentials**:
   - API URL (if required)
   - API Key / Username
   - API Secret / Password
   - Region (if applicable)
5. Click **"Test Connection"** to verify credentials
6. Click **"Save"** to create integration

**Permissions Required:** `integrations.manage`

---

### 2. Sync Integration Data

**Manual Sync:**
1. Locate integration card
2. Click **"Sync Now"** button
3. Watch progress bar (shows percentage)
4. Success notification appears when complete

**What Gets Synced:**
- Customer list (organizations/companies)
- Current device/endpoint status
- Active tickets/alerts
- Service configurations
- License information

**Sync Frequency:** 
- Automatic: Every 4 hours (configurable)
- Manual: On-demand via "Sync Now" button

**Permissions Required:** `integrations.sync`

---

### 3. Set Master Customer List (Source of Truth)

**Purpose:** Designates which integration provides the authoritative customer list. Other integrations' customers will map TO these customers.

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Set as Master Customer List"**
3. Confirm in dialog
4. Gold star badge appears on card

**Best Practice:** Choose your PSA (Autotask) or documentation system (ITGlue) as master.

**Permissions Required:** `integrations.manage`

---

### 4. Historical Data Import

**Purpose:** Import past data for trend analysis and historical reporting.

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Import Historical Data"**
3. Choose date range:
   - Last 30 days
   - Last 90 days
   - Last 6 months
   - Custom range
4. Click **"Start Import"**
5. Monitor progress in **"View Historical Jobs"**

**What Gets Imported:**
- Device uptime history
- Ticket history
- Alert/incident history
- Performance metrics
- Backup job results

**Import Duration:** 
- 30 days: ~10-30 minutes
- 90 days: ~1-2 hours
- 6 months: ~4-8 hours

**Note:** Imports run in background. You can close the page and check progress later.

**Permissions Required:** `integrations.manage`

---

### 5. View Historical Import Jobs

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"View Historical Jobs"**
3. See list of import jobs with:
   - Date range
   - Status (Running, Completed, Failed)
   - Progress percentage
   - Records imported
   - Start/end times

**Job Actions:**
- **Cancel**: Stop running import
- **Retry**: Restart failed import
- **Delete**: Remove completed job record

---

### 6. View Sync Logs

**Purpose:** Troubleshoot sync issues and view detailed sync history.

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"View Sync Logs"**
3. Review log entries showing:
   - Sync timestamp
   - Status (Success/Failed/Partial)
   - Records synced
   - Error messages (if any)
   - Duration

**Filtering:**
- Last 24 hours
- Last 7 days
- Last 30 days
- Failed only

---

### 7. Health Check

**Purpose:** Test if integration connection is still valid.

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Health Check"**
3. System tests:
   - API connectivity
   - Credential validity
   - Rate limit status
   - Data accessibility
4. View results with pass/fail indicators

**When to Use:**
- After credential changes
- When sync fails
- Periodic verification (monthly)

---

### 8. Rate Limit Status

**Purpose:** Monitor API usage to prevent rate limit violations.

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Rate Limit Status"**
3. View metrics:
   - Requests per minute (current/limit)
   - Requests per hour (current/limit)
   - Burst allowance remaining
   - Next reset time

**Indicators:**
- 🟢 Green: <50% usage (healthy)
- 🟡 Yellow: 50-80% usage (caution)
- 🔴 Red: >80% usage (throttling)

---

### 9. Edit Integration

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Edit"**
3. Modify:
   - Display name
   - Credentials
   - Sync schedule
   - Configuration options
4. Click **"Test Connection"** (recommended)
5. Click **"Save Changes"**

**Note:** Changing credentials requires re-authentication.

**Permissions Required:** `integrations.manage`

---

### 10. Preview Integration Data

**Purpose:** View raw customer data from integration before mapping.

**Steps:**
1. Click **"View Customers"** button on integration card
2. See list of customers with:
   - External ID
   - Customer name
   - Mapping status (Mapped/Unmapped)
   - Last seen timestamp
3. Click customer to view detailed data
4. Download data as Excel report

**Use Cases:**
- Verify sync is working
- Check customer names before mapping
- Export data for analysis

---

### 11. Delete Integration

**Steps:**
1. Click **three-dot menu** (⋮) on integration card
2. Select **"Delete"**
3. **Warning:** This will:
   - Remove integration connection
   - Unmap all customers from this integration
   - Delete historical data
   - Stop scheduled syncs
4. Type integration name to confirm
5. Click **"Delete Integration"**

**Cannot be undone!** Backup data first if needed.

**Permissions Required:** `integrations.manage`

---

## Search & Filtering

### Search Bar
- Type integration name or type
- Real-time filtering
- Case-insensitive
- Searches display name and integration type

### Status Filter
- **All**: Show all integrations
- **Active**: Successfully syncing
- **Disconnected**: Connection lost
- **Error**: Sync failures
- **Importing Historical**: Background import running

---

## Integration Status Indicators

| Status | Meaning | Action Required |
|--------|---------|-----------------|
| 🟢 **Active** | Syncing successfully | None |
| 🔴 **Disconnected** | Cannot connect to API | Check credentials, run health check |
| 🟡 **Error** | Sync failed | View sync logs, check rate limits |
| 🔵 **Importing Historical** | Background import running | Wait for completion or monitor progress |

---

## Common Tasks

### Adding Your First Integration
1. Click "Add Integration"
2. Choose Datto RMM or Autotask PSA
3. Enter API credentials from vendor portal
4. Test connection
5. Save and sync
6. Set as Master Customer List
7. Import 90 days of historical data

### Troubleshooting Sync Failures
1. Check integration status badge
2. View sync logs for error messages
3. Run health check
4. Verify credentials haven't expired
5. Check rate limit status
6. Re-save credentials if needed

### Preparing for Reports
1. Ensure all integrations are Active
2. Verify last sync was recent (<4 hours)
3. Check customer mapping is complete
4. Import historical data (90+ days recommended)
5. Verify data in Preview mode

---

## Best Practices

✅ **DO:**
- Set one integration as Master Customer List
- Import historical data for trend analysis
- Monitor sync logs weekly
- Run health checks monthly
- Keep credentials updated

❌ **DON'T:**
- Delete integrations without backing up data
- Change Master Customer List frequently
- Ignore sync failures
- Exceed API rate limits
- Share API credentials

---

## Permissions Reference

| Action | Required Permission |
|--------|-------------------|
| View integrations | `integrations.view` |
| Add integration | `integrations.manage` |
| Edit integration | `integrations.manage` |
| Delete integration | `integrations.manage` |
| Sync integration | `integrations.sync` |
| Set Master Customer List | `integrations.manage` |
| Import historical data | `integrations.manage` |
| View sync logs | `integrations.view` |
| Health check | `integrations.view` |

---

## Supported Integration Types

### PSA (Professional Services Automation)
- **Autotask**: Tickets, time entries, companies, resources
- **ConnectWise**: (Coming soon)

### RMM (Remote Monitoring & Management)
- **Datto RMM**: Devices, alerts, sites, uptime
- **Kaseya**: (Coming soon)

### Documentation
- **ITGlue**: Organizations, configurations, passwords, SSL certs

### Security
- **SaaS Alerts**: Security events, compliance monitoring
- **BitDefender**: Endpoints, threats, quarantine
- **Barracuda**: Email security, spam filtering

### Email Security
- **Sendmarc**: DMARC reports, sender statistics, domains

### Monitoring
- **LibreNMS**: Network devices, performance metrics, alerts

---

## Troubleshooting

### "Connection Failed" Error
**Cause:** Invalid credentials or API endpoint
**Solution:** 
1. Verify API key/credentials in vendor portal
2. Check API URL is correct
3. Ensure firewall allows outbound connections
4. Run health check for detailed error

### "Rate Limit Exceeded" Error
**Cause:** Too many API requests
**Solution:**
1. Check rate limit status
2. Wait for reset (shown in status)
3. Reduce sync frequency if recurring
4. Contact vendor for higher limits

### Historical Import Stuck
**Cause:** Large dataset or API throttling
**Solution:**
1. Check import progress in "View Historical Jobs"
2. Verify integration is still Active
3. Check rate limit status
4. Cancel and restart with smaller date range

### Customers Not Appearing
**Cause:** Sync hasn't run or failed
**Solution:**
1. Click "Sync Now"
2. Check sync logs for errors
3. Verify credentials are valid
4. Ensure integration status is Active

---

## FAQ

**Q: How often do integrations sync automatically?**
A: Every 4 hours by default. Configurable in Settings.

**Q: Can I have multiple integrations of the same type?**
A: Yes! Use different display names (e.g., "Production Datto", "Test Datto").

**Q: What happens if I delete an integration?**
A: All customer mappings from that integration are removed. Historical data is deleted. Cannot be undone.

**Q: How far back can I import historical data?**
A: Depends on integration type and vendor retention. Most support 90-365 days.

**Q: Why is my integration showing "Error" status?**
A: Check sync logs for specific error. Common causes: expired credentials, rate limits, API changes.

**Q: Can I pause an integration without deleting it?**
A: Not directly, but you can disable automatic syncing in Edit mode.

---

## Related Pages
- **Customers**: Map integration customers to unified customer records
- **Reports**: Generate reports using integration data
- **Settings**: Configure sync schedules and retention policies
- **Security**: Manage API keys and access controls
