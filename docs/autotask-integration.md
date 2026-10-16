# Autotask Integration Documentation

## Overview

The Autotask integration provides automated synchronization of PSA data including companies, tickets, time entries, and resources. The integration uses a hybrid polling strategy optimized for both freshness and API efficiency.

**Integration Type:** `Autotask`  
**API Documentation:** [AutotaskEndpoint.md](file:///home/mark/Music/reporting/backend/docs/AutotaskEndpoint.md)  
**API Base URL:** `https://webservices26.autotask.net/atservicesrest/v1.0`  
**Rate Limit:** 10,000 requests/hour

---

## Architecture

### Polling Strategy

The integration uses a **smart hybrid approach** combining database-driven scheduling with selective hard-coded schedules:

#### Database-Driven Schedules (Primary)
- **Command:** `integrations:evaluate-syncs` (runs every minute)
- **Autotask Schedule:** `*/10 * * * *` (every 10 minutes)
- **Stored in:** `integrations.sync_schedule` column

#### Hard-Coded Schedules (Exceptions)
- **Monthly Resources Sync:** 1st of month at 04:00
- **Purpose:** Refresh employee list (low-change data)

### Data Types Synced

| Data Type | Frequency | Method | Storage Table |
|-----------|-----------|--------|---------------|
| Companies | Every 10 mins | `sync()` | `integration_customers` |
| Tickets | Every 10 mins | `sync()` | `integration_data` |
| Time Entries | Every 10 mins | `sync()` | `integration_data` |
| Resources | Monthly | Historical import | `integration_data` |

---

## Critical Implementation Details

### Ticket Query Filter (IMPORTANT!)

**Issue Fixed:** The integration originally used `createDate` filter which only caught newly created tickets, missing all updates.

**Solution:** Changed to `lastActivityDate` filter to catch both created AND modified tickets.

**File:** [AutotaskClient.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskClient.php#L129-L130)

```php
// CORRECT (current implementation)
['field' => 'lastActivityDate', 'op' => 'gte', 'value' => $startDate]

// WRONG (old implementation) 
['field' => 'createDate', 'op' => 'gte', 'value' => $startDate]
```

**Impact:**
- Catches tickets with ANY activity (status changes, comments, assignments)
- More comprehensive than TTS-Security implementation
- Ensures no ticket updates are missed

**Note:** TTS-Security still uses `createDate` for both incremental and historical imports.

---

## Sync Methods

### 10-Minute Incremental Sync

**Method:** `AutotaskIntegration::sync()`  
**Trigger:** Database schedule `*/10 * * * *`  
**Window:** Last 10 minutes

**Process:**
1. Fetches all companies (full list, ~484 records)
2. Fetches tickets with `lastActivityDate` in last 10 mins
3. Fetches time entries with `dateWorked` in last 10 mins
4. Maps to customers from `integration_customers`
5. Stores in `integration_data` table

**Code Location:** [AutotaskIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskIntegration.php#L121-L197)

### Historical Import

**Method:** `AutotaskIntegration::getHistoricalData()`  
**Trigger:** Manual via UI or command  
**Command:** `php artisan integrations:import-historical {integration_id} --days={N}`

**Process:**
1. Processes date range in daily chunks
2. Fetches tickets with `lastActivityDate` in range
3. Fetches time entries with `dateWorked` in range
4. Fetches all active resources (employees)
5. Batch inserts (1000 records at a time)
6. Memory optimized with `unset()` and `gc_collect_cycles()`

**Code Location:** [AutotaskIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskIntegration.php#L222-L380)

### Monthly Resource Sync

**Schedule:** 1st of month at 04:00  
**Command:** `integrations:import-historical 1 --resources-only`  
**Purpose:** Refresh employee list without excessive API calls

**Code Location:** [routes/console.php](file:///home/mark/Music/reporting/backend/routes/console.php#L69-L73)

---

## Database Schema

### integration_customers
Stores Autotask companies (source of truth for customer data)

```sql
- integration_customer_id (PK)
- integration_id (FK to integrations)
- external_id (Autotask company ID)
- name (company name)
- customer_id (FK to customers - nullable until mapped)
- active (boolean)
- metadata (JSONB - full company data)
- last_seen_at (timestamp)
```

### integration_data
Stores tickets, time entries, and resources

```sql
- integration_data_id (PK)
- integration_id (FK to integrations)
- integration_customer_id (FK to integration_customers - nullable)
- data_type (enum: 'ticket', 'time_entry', 'resource')
- data_timestamp (timestamp - when record was created/modified)
- data_payload (JSONB - full record data)
- imported_at (timestamp)
- data_version (integer)
```

**Indexes:**
- `(integration_id, data_type, data_timestamp)` - for queries
- `(integration_id, data_type, imported_at)` - for recent data

---

## API Endpoints Used

### Companies Query
**Endpoint:** `POST /Companies/query`  
**Frequency:** Every 10 minutes  
**Filter:** `id > 0` (fetches all)  
**Returns:** ~484 companies  

### Tickets Query
**Endpoint:** `POST /Tickets/query`  
**Frequency:** Every 10 minutes  
**Filter:** `lastActivityDate >= [10 mins ago]`  
**Sort:** `lastActivityDate ASC`  
**Returns:** Tickets created OR modified in window

### Time Entries Query
**Endpoint:** `POST /TimeEntries/query`  
**Frequency:** Every 10 minutes  
**Filter:** `dateWorked >= [10 mins ago]`  
**Returns:** Time entries logged in window

### Resources Query
**Endpoint:** `POST /Resources/query`  
**Frequency:** Monthly  
**Filter:** `isActive = true`  
**Returns:** ~45 active employees

---

## Configuration

### Credentials

Required credentials in `integrations.credentials`:

```json
{
  "username": "api_user@domain.com",
  "secret": "api_secret_key",
  "api_integration_code": "INTEGRATION_CODE",
  "endpoint": "https://webservices26.autotask.net/atservicesrest/v1.0"
}
```

### Default Sync Schedule

**Set in 3 locations:**
1. [IntegrationController.php](file:///home/mark/Music/reporting/backend/app/Http/Controllers/Api/IntegrationController.php#L147) - `getDefaultSchedule()`
2. [IntegrationController.php](file:///home/mark/Music/reporting/backend/app/Http/Controllers/Api/IntegrationController.php#L379) - `createIntegration()`
3. [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php#L134) - `getDefaultSchedule()`

**Value:** `*/10 * * * *` (every 10 minutes)

---

## Rate Limit Analysis

### Autotask API Limits
- **Requests per hour:** 10,000
- **Recommended usage:** <8,000/hour for safety margin

### Current Usage (Estimated)

**Per 10-minute sync:**
- Companies: ~5 requests (pagination)
- Tickets: ~1 request (usually empty)
- Time entries: ~1 request (usually empty)
- **Subtotal:** ~7 requests

**Per hour:** 7 requests × 6 syncs = **~42 requests/hour**

**Monthly resource sync:** ~1 request (once per month, negligible)

### Headroom
- **Used:** 42 requests/hour (0.4%)
- **Available:** 9,958 requests/hour (99.6%)
- **Status:** Excellent - can add more data types

### Potential Additions
Safe to add without hitting limits:
- Ticket notes (incremental)
- Contacts (full sync)
- Contracts (full sync)
- Ticket history

---

## Testing & Monitoring

### Test 10-Minute Sync

**Trigger manual sync:**
```bash
docker exec app php artisan tinker --execute="
\$integration = \App\Models\Integration::find(1);
\$syncEngine = app(\App\Services\SyncEngine::class);
\$syncLog = \$syncEngine->triggerSync(\$integration, 'current_state', 'high');
echo 'Sync ID: ' . \$syncLog->sync_id;
"
```

**Check results:**
```sql
SELECT * FROM sync_logs 
WHERE integration_id = 1 
ORDER BY started_at DESC 
LIMIT 1;

SELECT COUNT(*) FROM integration_data 
WHERE integration_id = 1 
AND data_type = 'ticket';
```

### Monitor Sync Logs

**Watch worker logs:**
```bash
docker logs worker-1 -f | grep "Autotask"
```

**Check sync history:**
```sql
SELECT 
    started_at,
    status,
    records_synced,
    EXTRACT(EPOCH FROM (completed_at - started_at)) as duration_seconds
FROM sync_logs
WHERE integration_id = 1
ORDER BY started_at DESC
LIMIT 10;
```

### View Next Sync Time

Check integration card in UI - shows countdown timer to next scheduled sync.

---

## Common Scenarios

### Why 0 Records Synced?

**This is NORMAL and expected!**

The sync queries for tickets/time entries in the last 10 minutes. If there's no activity in that window:
- Tickets synced: 0
- Time entries synced: 0
- Companies synced: 484 (always checks full list)

**When it WILL sync data:**
- New ticket created → synced within 10 mins
- Existing ticket updated → synced within 10 mins  
- Time entry logged → synced within 10 mins

### Fresh Database Setup

**Steps performed:**
1. Stop workers to release DB connections
2. Drop database: `DROP DATABASE reporting WITH (FORCE);`
3. Recreate: `CREATE DATABASE reporting;`
4. Add TimescaleDB: `CREATE EXTENSION timescaledb;`
5. Run migrations: `php artisan migrate`
6. Seed data: `php artisan db:seed`
7. Restart workers

### Historical Import Results

**Last import (Nov 13 - Dec 13, 2025):**
- Companies: 484 (in `integration_customers`)
- Tickets: 41 (in `integration_data`)
- Time entries: 103 (in `integration_data`)
- Resources: 45 (in `integration_data`)
- **Total:** 673 unique records

**Note:** Import job showed 1,494 records processed - this is the cumulative progress counter including company checks across all days, not final unique records.

---

## Comparison: TTS-Security vs Current Implementation

| Feature | TTS-Security | Current Project |
|---------|-------------|-----------------|
| Ticket Filter | `createDate` | `lastActivityDate` |
| Catches New Tickets | Yes | Yes |
| Catches Ticket Updates | No | Yes |
| Catches Status Changes | No | Yes |
| Catches Comments | No | Yes |
| Incremental Sync | Manual only | Every 10 mins |
| Historical Import | Yes | Yes |
| API Efficiency | Lower | Higher |

**Key Advantage:** Our implementation catches ticket updates that TTS-Security misses, making it more comprehensive for tracking ticket lifecycle.

---

## Files Reference

### Core Integration Files
- [AutotaskIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskIntegration.php) - Main integration class
- [AutotaskClient.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskClient.php) - API client
- [AutotaskMapper.php](file:///home/mark/Music/reporting/backend/app/Integrations/Autotask/AutotaskMapper.php) - Data transformation

### Commands
- [PollAutotaskCommand.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/PollAutotaskCommand.php) - Manual polling command
- [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php) - Scheduler

### Jobs
- [SyncIntegrationJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/SyncIntegrationJob.php) - Sync job handler
- [ImportHistoricalDataJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/ImportHistoricalDataJob.php) - Historical import

### Configuration
- [routes/console.php](file:///home/mark/Music/reporting/backend/routes/console.php) - Schedule definitions

---

## Troubleshooting

### Sync Not Running

**Check schedule:**
```sql
SELECT integration_id, sync_schedule, last_sync_at, status 
FROM integrations 
WHERE integration_type = 'Autotask';
```

**Verify worker is running:**
```bash
docker ps | grep worker
```

**Check logs:**
```bash
docker logs worker-1 --tail 100 | grep -i autotask
```

### Rate Limit Errors

**Check rate limit status:**
```sql
SELECT 
    started_at,
    rate_limit_hits,
    error_messages
FROM sync_logs
WHERE integration_id = 1
AND rate_limit_hits > 0
ORDER BY started_at DESC;
```

**Solution:** Increase sync interval if hitting limits (unlikely at current 10-min frequency)

### Missing Tickets

**Verify filter:**
```bash
docker exec app php artisan tinker --execute="
\$integration = \App\Models\Integration::find(1);
\$client = new \App\Integrations\Autotask\AutotaskClient(\$integration->credentials, app(\App\Services\RateLimiter::class), \$integration);
\$tickets = \$client->getTickets(now()->subHours(24), now());
echo 'Tickets found (last 24hrs): ' . count(\$tickets);
"
```

**Expected:** Will find tickets created OR with activity in last 24 hours

---

## Future Enhancements

### Potential Additions
1. **Ticket Notes** - Track ticket communication
2. **Contacts** - Sync customer contacts
3. **Contracts** - Service agreements
4. **Ticket History** - Detailed change log
5. **Custom Fields** - UDF data

### Optimization Opportunities
1. Delta sync for companies (only changed records)
2. Webhook support for real-time updates
3. Batch ticket note fetching
4. Caching for resource lookups

---

## Support & Maintenance

### Regular Tasks
- **Monthly:** Resource sync runs automatically
- **Quarterly:** Review rate limit usage
- **Annually:** Audit data retention policies

### When to Run Historical Import
- After fresh database setup
- When onboarding many new employees
- To backfill data gaps
- After extended downtime

### Monitoring Checklist
- [ ] Verify syncs completing successfully
- [ ] Check for rate limit warnings
- [ ] Monitor database growth
- [ ] Review unmapped customer count

---

**Last Updated:** December 14, 2025  
**Integration Version:** 1.0  
**API Version:** v1.0
