# ITGlue Integration Documentation

## Overview

The ITGlue integration provides automated synchronization of IT documentation data including organizations, SSL certificates, domains, and license assets. Unlike Autotask and Datto which track operational activity, ITGlue is a **documentation platform** that stores static/snapshot data.

**Integration Type:** `ITGlue`  
**API Documentation:** [ITGlueEndpoints.md](file:///home/mark/Music/reporting/backend/docs/ITGlueEndpoints.md)  
**API Base URL:** `https://api.au.itglue.com` (region-specific)  
**Rate Limit:** 1,000 requests/hour

---

## Architecture

### Key Difference: Snapshot vs Activity Data

**ITGlue ≠ Autotask/Datto**

| Aspect | Autotask/Datto | ITGlue |
|--------|---------------|---------|
| **Purpose** | Track operations/activity | Document infrastructure |
| **Data Nature** | Time-series (tickets, alerts) | Snapshot (documentation) |
| **Change Frequency** | High (minutes/hours) | Low (days/months/years) |
| **Sync Strategy** | Frequent (5-10 mins) | Infrequent (6 hours) |
| **Historical Import** | Date range matters | Snapshot only (days ignored) |

### Polling Strategy

#### Database-Driven Schedule (Primary)
- **Command:** `integrations:evaluate-syncs` (runs every minute)
- **ITGlue Schedule:** `0 */6 * * *` (every 6 hours)
- **Stored in:** `integrations.sync_schedule` column

### Data Types Synced

| Data Type | Frequency | Change Rate | Storage Table |
|-----------|-----------|-------------|---------------|
| Organizations | Every 6 hours | Rare (new customers) | `integration_customers` |
| SSL Certificates | Every 6 hours | Rare (yearly renewal) | `integration_data` |
| Domains | Every 6 hours | Rare (yearly renewal) | `integration_data` |
| Flexible Assets (Licenses) | Every 6 hours | Occasional (license changes) | `integration_data` |

---

## Critical Implementation Details

### Regular Sync Now Includes Additional Data

**Recent Change:** Added `syncAdditionalData()` call to regular sync method.

**File:** [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php#L117-L119)

```php
// NEW: Sync additional data (SSL certs, domains, licenses)
// This tracks expiry dates and license utilization
$this->syncAdditionalData($errors);
```

### Why This Change Was Needed

**Before:**
- Regular sync: Organizations only
- SSL/domains/licenses: Historical import only
- **Problem:** No fresh expiry data for alerting

**After:**
- Regular sync: Organizations + SSL + Domains + Licenses
- **Benefit:** Fresh expiry tracking every 6 hours

### What syncAdditionalData() Fetches

**Code Location:** [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php#L155-L252)

1. **SSL Certificates** (line 158)
   - Filter: Expiring in next 90 days
   - Purpose: Track renewal dates
   - Critical for expiry alerting

2. **Domains** (line 170)
   - Filter: Expiring in next 90 days
   - Purpose: Track renewal dates
   - Critical for expiry alerting

3. **Flexible Assets** (lines 183-235)
   - Filter: License keywords only (`license`, `microsoft`, `office`, `o365`, `m365`, `subscription`, `azure`)
   - Skips non-license types (passwords, procedures, etc.)
   - Purpose: Track license utilization and costs

---

## Sync Methods

### 6-Hour Incremental Sync

**Method:** `ITGlueIntegration::sync()`  
**Trigger:** Database schedule `0 */6 * * *`  
**Fetches:** Current state snapshot

**Process:**
1. Fetches all organizations (full list)
2. Fetches SSL certificates expiring in next 90 days
3. Fetches domains expiring in next 90 days
4. Fetches license-related flexible assets only
5. Maps to customers from `integration_customers`
6. Stores in `integration_data` table

**Code Location:** [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php#L67-L153)

**Performance:**
- Duration: 30-60 seconds (with additional data)
- API calls: ~10-50 depending on data volume
- Safe under 1,000/hour rate limit

### Historical Import (Snapshot)

**Method:** `ITGlueIntegration::getHistoricalData()`  
**Trigger:** Manual via UI or command  
**Command:** `php artisan integrations:import-historical {integration_id} --days=1`

**Important:** The `--days` parameter is **ignored** for ITGlue because there's no historical time-series data. It always fetches current state.

**Process:**
1. Fetches organizations (current state)
2. Fetches SSL certificates expiring in next 90 days
3. Fetches domains expiring in next 90 days
4. Fetches ALL flexible asset types (not just licenses)
5. Stores snapshot in `integration_data`

**Code Location:** [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php#L269-L405)

**Key Difference from Regular Sync:**
- Regular sync: License flexible assets only
- Historical import: ALL flexible asset types

**Recommended Days:** `--days=1` (anything works, it's ignored)

---

## Database Schema

### integration_customers
Stores ITGlue organizations

```sql
- integration_customer_id (PK)
- integration_id (FK to integrations)
- external_id (ITGlue organization ID)
- name (organization name)
- customer_id (FK to customers - nullable until mapped)
- active (boolean)
- metadata (JSONB - full organization data)
- last_seen_at (timestamp)
```

### integration_data
Stores SSL certificates, domains, and flexible assets

```sql
- integration_data_id (PK)
- integration_id (FK to integrations)
- integration_customer_id (FK to integration_customers)
- data_type (enum: 'organization_info', 'ssl_certificate', 'domain', 'flexible_asset')
- data_timestamp (timestamp - certificate/domain expiry or asset update date)
- data_payload (JSONB - full data)
- imported_at (timestamp)
- data_version (integer)
```

**Indexes:**
- `(integration_id, data_type, data_timestamp)` - for expiry queries
- `(integration_id, integration_customer_id)` - for customer lookups

---

## API Endpoints Used

### Get Organizations
**Endpoint:** `GET /organizations`  
**Frequency:** Every 6 hours  
**Returns:** All organizations with details

### Get SSL Certificates
**Endpoint:** `GET /ssl_certificates?filter[expires_at_next_x_days]=90`  
**Frequency:** Every 6 hours  
**Filter:** Expiring in next 90 days  
**Returns:** SSL certificates with expiry dates

### Get Domains
**Endpoint:** `GET /domains?filter[expires_at_next_x_days]=90`  
**Frequency:** Every 6 hours  
**Filter:** Expiring in next 90 days  
**Returns:** Domains with expiry dates

### Get Flexible Asset Types
**Endpoint:** `GET /flexible_asset_types`  
**Frequency:** Every 6 hours  
**Returns:** All asset type definitions

### Get Flexible Assets
**Endpoint:** `GET /flexible_assets?filter[flexible_asset_type_id]={typeId}`  
**Frequency:** Every 6 hours  
**Filter:** By asset type ID, only license-related types processed  
**Returns:** Assets for specific type

---

## Configuration

### Credentials

Required credentials in `integrations.credentials`:

```json
{
  "api_key": "ITG.xxxxxxxx...",
  "base_url": "https://api.au.itglue.com"
}
```

**Regions:**
- Australia: `https://api.au.itglue.com`
- United States: `https://api.itglue.com`
- Europe: `https://api.eu.itglue.com`

### Default Sync Schedule

**Set in 2 locations:**
1. [IntegrationController.php](file:///home/mark/Music/reporting/backend/app/Http/Controllers/Api/IntegrationController.php) - `getDefaultSchedule()`
2. [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php) - `getDefaultSchedule()`

**Value:** `0 */6 * * *` (every 6 hours, on the hour)

---

## Rate Limit Analysis

### ITGlue API Limits
- **Requests per hour:** 1,000
- **Recommended usage:** <800/hour for safety margin

### Current Usage (Estimated)

**Per 6-hour sync:**
- Get organizations: 1-2 requests
- Get SSL certificates: 1-2 requests
- Get domains: 1-2 requests
- Get flexible asset types: 1 request
- Get flexible assets: 5-20 requests (license types only)
- **Subtotal per sync:** ~10-30 requests

**Per hour:** ~10-30 requests / 6 hours = **~2-5 requests/hour**

### Headroom
- **Used:** ~5 requests/hour (0.5%)
- **Available:** 995 requests/hour (99.5%)
- **Status:** Excellent - lots of capacity

---

## License Display Feature

### Custom Display Template

ITGlue includes a special "Licenses" column in the customer table that opens a modal showing license details.

**Code Location:** [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php#L458-L502)

**How it works:**
1. Customer table shows "View Licenses" button
2. Click → calls `getCustomerLicenses()` (line 507)
3. Fetches license flexible assets for that customer
4. Displays: Name, Active, Consumed, Unused, Utilization %

**License Data Mapping:**
```php
[
    'name' => 'Microsoft 365 Business Premium',
    'active' => 50,      // Total seats purchased
    'consumed' => 45,    // Seats assigned/used
    'unused' => 5,       // Available seats
    'utilization' => 90.0 // Percentage used
]
```

---

## Testing & Monitoring

### Test 6-Hour Sync

**Trigger manual sync:**
```bash
docker exec app php artisan tinker --execute="
\$integration = \App\Models\Integration::where('integration_type', 'ITGlue')->first();
\$syncEngine = app(\App\Services\SyncEngine::class);
\$syncLog = \$syncEngine->triggerSync(\$integration, 'current_state', 'high');
echo 'Sync ID: ' . \$syncLog->sync_id;
"
```

**Check results:**
```sql
SELECT * FROM sync_logs 
WHERE integration_id = (SELECT integration_id FROM integrations WHERE integration_type = 'ITGlue') 
ORDER BY started_at DESC 
LIMIT 1;

-- Check data breakdown
SELECT data_type, COUNT(*) 
FROM integration_data 
WHERE integration_id = (SELECT integration_id FROM integrations WHERE integration_type = 'ITGlue')
GROUP BY data_type;
```

### Monitor SSL/Domain Expiry

**Find expiring soon:**
```sql
SELECT 
    ic.name as customer_name,
    id.data_type,
    id.data_payload->>'name' as item_name,
    id.data_timestamp as expires_at,
    EXTRACT(DAY FROM (id.data_timestamp - NOW())) as days_until_expiry
FROM integration_data id
JOIN integration_customers ic ON id.integration_customer_id = ic.integration_customer_id
WHERE id.integration_id = (SELECT integration_id FROM integrations WHERE integration_type = 'ITGlue')
AND id.data_type IN ('ssl_certificate', 'domain')
AND id.data_timestamp <= NOW() + INTERVAL '30 days'
ORDER BY id.data_timestamp;
```

### Monitor License Utilization

**Find over-utilized licenses:**
```sql
SELECT 
    ic.name as customer_name,
    id.data_payload->'traits'->>'license-name' as license_name,
    (id.data_payload->'traits'->>'consumed')::int as consumed,
    (id.data_payload->'traits'->>'active')::int as total,
    ROUND((id.data_payload->'traits'->>'consumed')::numeric / 
          NULLIF((id.data_payload->'traits'->>'active')::numeric, 0) * 100, 1) as utilization
FROM integration_data id
JOIN integration_customers ic ON id.integration_customer_id = ic.integration_customer_id
WHERE id.integration_id = (SELECT integration_id FROM integrations WHERE integration_type = 'ITGlue')
AND id.data_type = 'flexible_asset'
AND id.data_payload->>'type_name' ILIKE '%license%'
AND (id.data_payload->'traits'->>'consumed')::numeric > (id.data_payload->'traits'->>'active')::numeric * 0.9
ORDER BY utilization DESC;
```

---

## Common Scenarios

### Why Only 6-Hour Sync Frequency?

**ITGlue data changes infrequently:**
- Organizations: New customers added monthly
- SSL certificates: Renewed yearly
- Domains: Renewed yearly
- Licenses: Changed occasionally (monthly/quarterly)

**6 hours is plenty to:**
- Catch new organizations
- Track certificate/domain expiry for alerting
- Monitor license changes

**Compare to:**
- Autotask: 10 min (high activity - tickets)
- Datto: 5 min (high activity - alerts)
- ITGlue: 6 hours (low activity - documentation)

### Historical Import vs Regular Sync

**Both fetch same data types, but:**

| | Regular Sync | Historical Import |
|---|---|---|
| Frequency | Every 6 hours | Manual/one-time |
| Organizations | All | All |
| SSL Certificates | 90-day window | 90-day window |
| Domains | 90-day window | 90-day window |
| Flexible Assets | License types only | ALL types |
| Purpose | Keep current | Initial population |

**When to run historical import:**
- After fresh database setup
- When adding ITGlue integration
- To backfill all asset types (not just licenses)

### No New Data in Sync?

**This is NORMAL for ITGlue!**

Unlike Autotask/Datto where you expect new tickets/alerts frequently, ITGlue data is mostly static:
- `records_synced = 0` is common if nothing changed
- Only expect changes when:
  - New customer added
  - Certificate renewed
  - Domain renewed
  - License modified

---

## Troubleshooting

### Sync Failing with 401 Unauthorized

**Check API key:**
```bash
docker exec app php artisan tinker --execute="
\$itglue = \App\Models\Integration::where('integration_type', 'ITGlue')->first();
\$client = new \App\Integrations\ITGlue\ITGlueClient(\$itglue->credentials, app(\App\Services\RateLimiter::class), \$itglue);
\$result = \$client->testConnection();
echo \$result ? 'Connected ' : 'Failed ';
"
```

**Common issues:**
- Wrong API key
- Wrong region (AU vs US vs EU)
- API key expired/revoked

### No SSL Certificates or Domains

**Check if they exist in ITGlue:**
- SSL certificates must have expiry dates
- Must be expiring within next 90 days
- Check ITGlue UI for actual data

**Verify filter:**
```bash
docker exec app php artisan tinker --execute="
\$itglue = \App\Models\Integration::where('integration_type', 'ITGlue')->first();
\$client = new \App\Integrations\ITGlue\ITGlueClient(\$itglue->credentials, app(\App\Services\RateLimiter::class), \$itglue);
\$certs = \$client->getSslCertificates('next90days');
echo 'SSL Certs found: ' . count(\$certs);
"
```

### No Licenses in Flexible Assets

**Check filter logic:**

Line 185-211 filters flexible asset types by keywords:
```php
$licenseKeywords = ['license', 'microsoft', 'office', 'o365', 'm365', 'subscription', 'azure'];
```

**If your asset types use different names:**
- Add keywords to the array
- Or remove filter to fetch all types (may be heavy)

---

## Files Reference

### Core Integration Files
- [ITGlueIntegration.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueIntegration.php) - Main integration class
- [ITGlueClient.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueClient.php) - API client
- [ITGlueMapper.php](file:///home/mark/Music/reporting/backend/app/Integrations/ITGlue/ITGlueMapper.php) - Data transformation

### Commands
- [EvaluateIntegrationSyncs.php](file:///home/mark/Music/reporting/backend/app/Console/Commands/EvaluateIntegrationSyncs.php) - Scheduler

### Jobs
- [SyncIntegrationJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/SyncIntegrationJob.php) - Sync job handler
- [ImportHistoricalDataJob.php](file:///home/mark/Music/reporting/backend/app/Jobs/ImportHistoricalDataJob.php) - Historical import

### Configuration
- [routes/console.php](file:///home/mark/Music/reporting/backend/routes/console.php) - Schedule definitions

---

## Integration Comparison Summary

### Data Sync Patterns

| Integration | Sync Frequency | Data Type | Change Rate | Why |
|-------------|---------------|-----------|-------------|-----|
| **Autotask** | 10 minutes | Companies, Tickets, Time | High | Operational activity tracking |
| **Datto** | 5 minutes | Customers, Alerts | High | Real-time monitoring |
| **ITGlue** | 6 hours | Orgs, SSL, Domains, Licenses | Low | Static documentation |

### When to Use Each Pattern

**High-Frequency (5-10 min):**
- Activity-based systems
- Real-time operational data
- Time-sensitive alerting
- Examples: PSA tickets, RMM alerts

**Low-Frequency (6+ hours):**
- Documentation systems
- Static/snapshot data
- Infrequent changes
- Examples: Asset inventory, certificates

---

## Future Enhancements

### Potential Additions
1. **Passwords** - Track password age/rotation
2. **Contacts** - Key contact information
3. **Network Diagrams** - Documentation links
4. **Configurations** - Current state snapshots
5. **More Flexible Asset Types** - Expand beyond licenses

### Optimization Opportunities
1. **Conditional sync** - Only fetch if ITGlue data changed
2. **Webhook support** - Real-time updates if ITGlue adds webhooks
3. **Selective expiry windows** - Different windows for SSL (30d) vs domains (90d)
4. **License alerting** - Auto-alert when utilization > 90%

---

## Support & Maintenance

### Regular Tasks
- **Weekly:** Review expiring SSL certificates (30-day window)
- **Weekly:** Review expiring domains (30-day window)
- **Monthly:** Review license utilization trends
- **Quarterly:** Audit flexible asset types synced

### When to Run Historical Import
- After fresh database setup
- When adding ITGlue integration
- To populate all asset types (not just licenses)
- Anytime - it's just a snapshot

### Monitoring Checklist
- [ ] Verify 6-hour syncs completing successfully
- [ ] Check SSL certificate count matches ITGlue
- [ ] Check domain count matches ITGlue
- [ ] Verify license data accuracy
- [ ] Monitor rate limit usage (should be <1%)

---

**Last Updated:** December 14, 2025  
**Integration Version:** 1.1 (added syncAdditionalData to regular sync)  
**API Version:** v1
