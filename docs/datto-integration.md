# Datto RMM Integration Documentation

## Overview

The Datto RMM integration provides automated synchronization of customer sites, devices, and alerts. The integration uses a hybrid polling strategy optimized for alert freshness while managing device data separately to prevent rate limits.

**Integration Type:** `Datto`  
**API Documentation:** [DattoRMMEndpoints.md](file:///home/mark/Music/reporting/backend/docs/DattoRMMEndpoints.md)  
**API Base URL:** `https://api.datto.com/v1/rmm`  
**Rate Limit:** 1,000 requests/hour

---

## Architecture

### Polling Strategy

The integration uses a **smart hybrid approach** combining database-driven and hard-coded schedules:

#### Database-Driven Schedules (Primary)
- **Command:** `integrations:evaluate-syncs` (runs every minute)
- **Datto Schedule:** `*/5 * * * *` (every 5 minutes)
- **Purpose:** Customers + Alerts (lightweight, frequent)
- **Stored in:** `integrations.sync_schedule` column

#### Hard-Coded Schedules (Exceptions)
- **Device Polling:** Every 30 minutes via `datto:poll-devices`
- **Purpose:** Heavy device queries separated to avoid rate limits

### Data Types Synced

| Data Type | Frequency | Method | Storage Table |
|-----------|-----------|--------|---------------|
| Customers/Sites | Every 5 mins | `sync()` | `integration_customers` |
| Open Alerts | Every 5 mins | `sync()` | `integration_data` |
| Resolved Alerts | Every 5 mins (1hr window) | `sync()` | `integration_data` |
| Devices | Every 30 mins | `datto:poll-devices` | `integration_data` |

---

## Critical Implementation Details

### Alert Polling Strategy (1-Hour Window)

**Key Design Decision:** Every 5-minute sync fetches resolved alerts from the **last 1 hour**, not just last 5 minutes.

**File:** [DattoIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoIntegration.php#L172)

```php
// Get resolved alerts from last 1 hour (same as TTS)
$resolvedAlerts = $this->client->getRmmAlerts($siteUid, false, now()->subHour());
```

### Why 1-Hour Window?

**Redundancy for Reliability:**
- Each resolved alert is fetched ~12 times (every 5 mins for 1 hour)
- Ensures no alerts missed if a sync fails
- Handles late-appearing or backdated alerts
- **Matches TTS-Security pattern exactly** 
**Trade-off:**
- More API calls (12x redundancy)
- More database `updateOrCreate` checks
- **Reliability** - guarantees no missing alerts
- Deduplication prevents actual duplicates in DB

**Performance Impact:**
- **Current:** 0 alerts in last hour = no overhead
- **High volume:** Would process duplicates but DB handles it
- **Safety over efficiency** - intentional design

---

## Sync Methods

### 5-Minute Incremental Sync

**Method:** `DattoIntegration::sync()`  
**Trigger:** Database schedule `*/5 * * * *`  
**Window:** 
- Customers: Full list
- Open alerts: All current
- Resolved: Last 1 hour

**Process:**
1. Fetches all customers/sites (full list, ~110 records)
2. For each site:
   - Fetches all open alerts
   - Fetches resolved alerts from last 1 hour
3. Stores alerts in `integration_data` table
4. Auto-maps customers from `integration_customers`

**Code Location:** [DattoIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoIntegration.php#L78-L200)

**Example Sync Result:**
- 110 customers synced
- 13,756 alerts synced
- Total: 13,866 records

### Device Polling (30-Minute Schedule)

**Method:** `DattoIntegration::pollDevices()`  
**Trigger:** Hard-coded schedule in `routes/console.php`  
**Command:** `datto:poll-devices`  
**Frequency:** Every 30 minutes

**Why Separated:**
- Device queries are heavy/slow
- Prevents rate limit issues
- Devices change infrequently
- Allows alert polling to remain fast (5 mins)

**Code Location:** [routes/console.php](file:///home/mark/Music/reporting/backend/routes/console.php#L64-L67)

### Historical Import

**Method:** `DattoIntegration::getHistoricalData()`  
**Trigger:** Manual via UI or command  
**Command:** `php artisan integrations:import-historical {integration_id} --days={N}`

**Process:**
1. Fetches current device snapshots for all sites
2. Fetches all open alerts (current state)
3. Fetches resolved alerts since start date (full range)
4. Stores everything in `integration_data`

**Code Location:** [DattoIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoIntegration.php#L229-L340)

**What It Fetches:**
- Devices (current snapshot)
- Open alerts (current state)
- Resolved alerts (historical range)
- Customers (already in regular sync)

---

## Database Schema

### integration_customers
Stores Datto customer sites

```sql
- integration_customer_id (PK)
- integration_id (FK to integrations)
- external_id (Datto site UID)
- name (site name)
- customer_id (FK to customers - nullable until mapped)
- active (boolean)
- metadata (JSONB - full site data)
- last_seen_at (timestamp)
```

### integration_data
Stores alerts and devices

```sql
- integration_data_id (PK)
- integration_id (FK to integrations)
- integration_customer_id (FK to integration_customers - nullable)
- data_type (enum: 'alert', 'device', 'device_uptime')
- data_timestamp (timestamp - when alert/device was created/updated)
- data_payload (JSONB - full alert/device data)
- imported_at (timestamp)
- data_version (integer)
```

**Indexes:**
- `(integration_id, data_type, data_timestamp)` - for queries
- `(integration_id, data_type, imported_at)` - for recent data

---

## API Endpoints Used

### Get Customers/Sites
**Endpoint:** `GET /account/sites`  
**Frequency:** Every 5 minutes  
**Returns:** ~110 customer sites

### Get Open Alerts
**Endpoint:** `GET /site/{siteUid}/alerts/open`  
**Frequency:** Every 5 minutes (per site)  
**Returns:** All currently active alerts for site

### Get Resolved Alerts
**Endpoint:** `GET /site/{siteUid}/alerts/resolved?resolvedSince={timestamp}`  
**Frequency:** Every 5 minutes (per site)  
**Filter:** `resolvedSince = now() - 1 hour`  
**Returns:** Alerts resolved in last hour

### Get Devices
**Endpoint:** `GET /site/{siteUid}/devices`  
**Frequency:** Every 30 minutes (separate command)  
**Returns:** All devices for site with detailed metrics

---

## Configuration

### Credentials

Required credentials in `integrations.credentials`:

```json
{
  "api_url": "https://api.datto.com",
  "api_key": "your-api-key",
  "api_secret": "your-api-secret"
}
```

### Default Sync Schedule

**Set in 2 locations:**
1. [IntegrationController.php](file:///home/mark/Music/reporting/backend/app/Http/Controllers/Api/IntegrationController.php) - `getDefaultSchedule()`
2. [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php) - `getDefaultSchedule()`

**Value:** `*/5 * * * *` (every 5 minutes)

---

## Rate Limit Analysis

### Datto API Limits
- **Requests per hour:** 1,000
- **Recommended usage:** <800/hour for safety margin

### Current Usage (Estimated)

**Per 5-minute sync:**
- Get sites: 1 request
- Get alerts per site: 2 requests × 110 sites = 220 requests
- **Subtotal per sync:** ~221 requests

**Per hour:** 221 requests × 12 syncs = **~2,652 requests/hour** Warning: **Per 30-minute device poll:**
- Get devices per site: 1 request × 110 sites = 110 requests
- **Subtotal per poll:** ~110 requests

**Per hour (devices):** 110 requests × 2 polls = **~220 requests/hour**

### Total Hourly Usage
- **Alert syncs:** 2,652 requests/hour
- **Device polls:** 220 requests/hour
- **Total:** ~2,872 requests/hour
- **Headroom:** Warning: Using 287% of 1,000 limit!

### Rate Limit Management

**Current Protection:**
- Rate limiter built into API client
- Exponential backoff on failures
- Job release back to queue on rate limit hit

**If Hitting Limits:**
1. Increase sync interval (5 min → 10 min)
2. Reduce alert window (1 hour → 10 mins)
3. Poll fewer sites per batch

---

## Performance Analysis

### Current Database State

**After Initial Sync:**
```
Customers:        110
Alerts:        13,756
Total:         13,866 records
```

**Alert Age Distribution:**
- Last hour: 0 alerts (0%)
- Last 10 mins: 0 alerts (0%)
- Oldest: June 13, 2025
- Newest: Dec 14, 2025 10:24 AM

**Observation:** Most alerts are historical (from first sync). Future 5-min syncs will fetch very few alerts (0-100 typically).

### Sync Performance

**First "Sync Now":**
- Duration: ~30-60 seconds
- Records: 13,866
- Status: Success 
**Future 5-Min Syncs:**
- Expected duration: 5-15 seconds
- Expected records: 0-100 (only new/changed alerts)
- Performance: Fast 
---

## Alert Overlap Example

**Understanding the 1-Hour Window Redundancy:**

Alert resolved at 10:00 AM:

```
10:05 sync: Fetches 09:05-10:05 → processes alert (1st time)
10:10 sync: Fetches 09:10-10:10 → processes alert (2nd time)
10:15 sync: Fetches 09:15-10:15 → processes alert (3rd time)
...continues every 5 mins...
10:55 sync: Fetches 09:55-10:55 → processes alert (11th time)
11:00 sync: Fetches 10:00-11:00 → processes alert (12th time)
11:05 sync: Fetches 10:05-11:05 → alert no longer in window
```

**Result:** Same alert fetched **12 times** before it ages out.

**Database Handling:**
```php
// Uses updateOrCreate - prevents duplicates
$this->storeAlertData($alert, $site);
// First time: INSERT
// Subsequent 11 times: UPDATE (no-op if unchanged)
```

---

## Testing & Monitoring

### Test 5-Minute Sync

**Trigger manual sync:**
```bash
docker exec app php artisan tinker --execute="
\$integration = \App\Models\Integration::find(2);
\$syncEngine = app(\App\Services\SyncEngine::class);
\$syncLog = \$syncEngine->triggerSync(\$integration, 'current_state', 'high');
echo 'Sync ID: ' . \$syncLog->sync_id;
"
```

**Check results:**
```sql
SELECT * FROM sync_logs 
WHERE integration_id = 2 
ORDER BY started_at DESC 
LIMIT 1;

SELECT data_type, COUNT(*) 
FROM integration_data 
WHERE integration_id = 2 
GROUP BY data_type;
```

### Monitor Alert Volume

**Check alert freshness:**
```sql
SELECT 
    data_type,
    COUNT(*) as total,
    COUNT(CASE WHEN data_timestamp >= NOW() - INTERVAL '1 hour' THEN 1 END) as last_hour,
    COUNT(CASE WHEN data_timestamp >= NOW() - INTERVAL '10 minutes' THEN 1 END) as last_10min
FROM integration_data
WHERE integration_id = 2
GROUP BY data_type;
```

### Monitor Sync Performance

**Watch worker logs:**
```bash
docker logs worker-1 -f | grep "Datto"
```

**Check sync duration:**
```sql
SELECT 
    started_at,
    EXTRACT(EPOCH FROM (completed_at - started_at)) as duration_seconds,
    records_synced,
    status
FROM sync_logs
WHERE integration_id = 2
ORDER BY started_at DESC
LIMIT 10;
```

---

## Comparison: TTS-Security vs Current Implementation

| Feature | TTS-Security | Current Project |
|---------|-------------|-----------------|
| Alert Window | 1 hour | 1 hour |
| Sync Frequency | 5 minutes | 5 minutes |
| Open Alerts | Yes | Yes |
| Resolved Alerts | Yes (1hr) | Yes (1hr) |
| Device Separation | Yes (30 min) | Yes (30 min) |
| Redundancy Strategy | 12x overlap | 12x overlap |
| Deduplication | Yes | Yes |

**Verdict:** Implementation **matches TTS-Security exactly** 
---

## Common Scenarios

### Why So Many Alerts on First Sync?

**This is NORMAL!**

First "Sync Now" fetches:
- All open alerts (current state)
- All resolved alerts from last 1 hour
- **Result:** Mix of historical + current = large number (e.g., 13,756)

Future syncs only fetch:
- Open alerts (if still open)
- Newly resolved alerts from last hour
- **Result:** Much smaller (0-100 typically)

### Why 0 Records Synced Sometimes?

**Also normal!**

If there's no alert activity in the last hour:
- Open alerts: May stay same (no new inserts)
- Resolved alerts: 0 in last hour
- **Result:** Records synced = 0 (or just customer updates)

### Device Data Missing?

**Check separate schedule:**

Devices sync every 30 minutes via separate command, not in the 5-min sync.

```bash
# Check device poll logs
docker logs worker-1 | grep "datto:poll-devices"

# Check device count
SELECT COUNT(*) FROM integration_data 
WHERE integration_id = 2 AND data_type = 'device';
```

---

## Troubleshooting

### Rate Limit Errors

**Symptoms:**
- Syncs failing with "429 Too Many Requests"
- Logs showing rate limit warnings

**Check usage:**
```sql
SELECT 
    started_at,
    rate_limit_hits,
    error_messages
FROM sync_logs
WHERE integration_id = 2
AND rate_limit_hits > 0
ORDER BY started_at DESC;
```

**Solutions:**
1. Reduce sync frequency (5min → 10min)
2. Reduce alert window (1hr → 10min)
3. Poll fewer sites per batch

### Sync Not Running

**Check schedule:**
```sql
SELECT integration_id, sync_schedule, last_sync_at, status 
FROM integrations 
WHERE integration_type = 'Datto';
```

**Verify worker:**
```bash
docker ps | grep worker
docker logs worker-1 --tail 50 | grep -i datto
```

### Missing Alerts

**Verify API access:**
```bash
docker exec app php artisan tinker --execute="
\$integration = \App\Models\Integration::find(2);
\$client = new \App\Integrations\Datto\DattoClient(\$integration->credentials, app(\App\Services\RateLimiter::class), \$integration);
\$sites = \$client->getRmmSites();
echo 'Sites found: ' . count(\$sites);
"
```

**Check alert window:**
- Resolved alerts only go back 1 hour
- Older alerts won't appear in regular sync
- Use historical import for older data

---

## Files Reference

### Core Integration Files
- [DattoIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoIntegration.php) - Main integration class
- [DattoClient.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoClient.php) - API client
- [DattoMapper.php](file:///home/mark/Music/reporting/backend/app/Integrations/Datto/DattoMapper.php) - Data transformation

### Commands
- [PollDattoDevicesCommand.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/PollDattoDevicesCommand.php) - Device polling
- [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php) - Scheduler

### Jobs
- [SyncIntegrationJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/SyncIntegrationJob.php) - Sync job handler
- [ImportHistoricalDataJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/ImportHistoricalDataJob.php) - Historical import

### Configuration
- [routes/console.php](file:///home/mark/Music/reporting/backend/routes/console.php) - Schedule definitions

---

## Future Enhancements

### Potential Optimizations
1. **Reduce Alert Window** - Change from 1hr to 10min (reduce redundancy)
2. **Track Last Sync** - Use `last_sync_at` instead of fixed window
3. **Webhook Support** - Real-time alerts if Datto API adds it
4. **Batch Site Processing** - Process sites in batches to reduce rate limit impact

### Additional Data Types
1. **Audit Logs** - Device access/change logs
2. **Components** - Hardware inventory
3. **Software Inventory** - Installed applications
4. **Security Events** - Antivirus/security alerts

---

## Support & Maintenance

### Regular Tasks
- **Daily:** Monitor alert volume and sync success rate
- **Weekly:** Check rate limit usage
- **Monthly:** Review alert trends and device count

### When to Run Historical Import
- After fresh database setup
- To backfill alert history
- When adding new Datto instance
- After extended downtime

### Monitoring Checklist
- [ ] Verify 5-min syncs completing successfully
- [ ] Check device polls running every 30 mins
- [ ] Monitor rate limit warnings
- [ ] Review unmapped customer count
- [ ] Check alert freshness (should be <1 hour old)

---

**Last Updated:** December 14, 2025  
**Integration Version:** 1.0  
**API Version:** v1
