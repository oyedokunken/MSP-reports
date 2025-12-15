# TimescaleDB Integration Best Practices

> **Add this section to all integration documentation files (Datto, Autotask, ITGlue, LibreNMS, etc.)**

---

## TimescaleDB Data Storage Pattern

### The Golden Rule

**Always `create()` for time-series data, never `updateOrCreate()`**

### Why This Matters

**TimescaleDB Constraint:**
- Data partitioned by timestamp into daily chunks
- Rows CANNOT move between chunks after insertion
- Updating `data_timestamp` → partition violation error

**Example Error (What We Fixed):**
```
SQLSTATE[23514]: Check violation: 7 ERROR: 
new row for relation "_hyper_X_Y_chunk" violates check constraint "constraint_Z"
```

---

## Time-Series Snapshot Pattern

### How It Works

**Every sync creates a NEW record:**

```php
// Sync at 10:00 AM
IntegrationData::create([
    'integration_id' => 2,
    'data_type' => 'alert',
    'data_timestamp' => '2025-12-15 10:00:00',
    'data_payload' => ['device_id' => 'abc123', 'status' => 'online']
]);

// Sync at 10:10 AM (same device, same status)
IntegrationData::create([
    'integration_id' => 2,
    'data_type' => 'alert',
    'data_timestamp' => '2025-12-15 10:10:00',
    'data_payload' => ['device_id' => 'abc123', 'status' => 'online']  // "Duplicate"!
]);
```

**Result:** 2 records in database

### Why "Duplicates" Are Good

**Benefits of time-series snapshots:**

1. **Audit Trail** - Prove monitoring happened
   ```
   "Device was online at 10:00 ✓"
   "Device was online at 10:10 ✓"
   "Device still online at 10:20 ✓"
   ```

2. **Change Detection** - Know EXACTLY when state changed
   ```
   10:00: status = online
   10:10: status = online
   10:20: status = offline  ← Changed between 10:10-10:20!
   ```

3. **SLA Compliance** - Calculate uptime percentage
   ```sql
   SELECT COUNT(*) FILTER (WHERE status = 'online') / COUNT(*) * 100 
   FROM checks WHERE device_id = 'abc123' AND timestamp > now() - interval '30 days'
   ```

4. **Historical Analysis** - Track trends over time

---

## Database Efficiency

### Compression Magic

**TimescaleDB automatically compresses identical data:**

```
Device abc123, status: online
├── 10:00:00 → Full record (~3 KB)
├── 10:10:00 → Compressed reference (~100 bytes)
├── 10:20:00 → Compressed reference (~100 bytes)
└── 10:30:00 → Compressed reference (~100 bytes)
```

**Real Example (Current Database):**
- 17,294 records stored
- Uncompressed size: ~50 MB
- Compressed size: **64 KB**
- **Compression ratio:** 99.8%

### Storage Growth Projection

**With continuous syncing:**

| Period | Uncompressed | Compressed | Savings |
|--------|-------------|------------|---------|
| Day | 3.5 MB | 350 KB | 90% |
| Month | 105 MB | 10 MB | 90% |
| Year | 3.5 GB | 120 MB | 97% |

**Conclusion:** "Duplicates" add minimal storage cost!

---

## Viewing Latest Data

### Reports Show Deduplicated View

**Query pattern:**
```php
// Get latest snapshot per device
$latestDevices = IntegrationData::where('data_type', 'device_status')
    ->orderBy('data_timestamp', 'desc')
    ->get()
    ->unique('data_payload.device_id');  // Show latest only

// User sees: 100 devices
// Database has: 10,000 records (snapshots over time)
```

**SQL equivalent:**
```sql
SELECT DISTINCT ON (data_payload->>'device_id')
    data_payload->>'device_id' as device_id,
    data_payload->>'status' as status,
    data_timestamp
FROM integration_data
WHERE data_type = 'device_status'
ORDER BY data_payload->>'device_id', data_timestamp DESC;
```

---

## Customer Matching Pattern

### Batch Processing (Efficient)

**Use `BatchAutoMatchCustomersJob` at end of sync:**

```php
// In sync() method
public function sync(): array
{
    // ... fetch and store data ...
    
    // Process unmapped customers
    $additionalMapped = $this->processUnmappedCustomers();
    
    return [...];
}

// processUnmappedCustomers() method
private function processUnmappedCustomers(): array
{
    // Single batch job for all unmapped customers
    BatchAutoMatchCustomersJob::dispatch($this->integration);
    return [];
}
```

**Benefits:**
- 1 job instead of 100+ individual jobs
- Reduces queue congestion
- Better performance at scale
- All integrations use same pattern

**Replaced pattern (OLD, DO NOT USE):**
```php
// ❌ OLD: Individual jobs per customer
foreach ($unmappedCustomers as $customer) {
    AutoMatchCustomersJob::dispatch($customer);  // Creates 100+ jobs!
}
```

---

## Code Examples by Integration

### Datto: Alerts and Devices

```php
// storeHistoricalData() - CORRECT
private function storeHistoricalData(array $data): void
{
    IntegrationData::create([
        'company_id' => $this->integration->company_id,
        'integration_id' => $this->integration->integration_id,
        'integration_customer_id' => $data['integration_customer_id'],
        'data_type' => $data['data_type'],
        'data_timestamp' => $data['timestamp'],
        'data_payload' => $data['payload'],
        'imported_at' => now(),
        'data_version' => 1,
    ]);
}
```

### Autotask: Tickets and Time Entries

```php
// storeHistoricalDataBatch() - CORRECT
public function storeHistoricalDataBatch(array $records): void
{
    if (empty($records)) return;
    
    // TimescaleDB hypertable - simple batch insert
    DB::table('integration_data')->insert($records);
}
```

### ITGlue: SSL Certificates and Domains

```php
// storeHistoricalData() - CORRECT
private function storeHistoricalData(array $data): void
{
    IntegrationData::create([
        'company_id' => $this->integration->company_id,
        'integration_id' => $this->integration->integration_id,
        'integration_customer_id' => $data['integration_customer_id'],
        'data_type' => $data['data_type'],
        'data_timestamp' => $data['timestamp'],
        'data_payload' => $data['payload'],
        'imported_at' => now(),
        'data_version' => 1,
    ]);
}
```

---

## What Changed (December 2025)

### Issues Fixed

1. ❌ **Datto device_status** - Used ON CONFLICT → Removed
2. ❌ **Datto alerts** - Used ON CONFLICT → Removed  
3. ❌ **LibreNMS devices** - Used updateOrCreate() → Changed to create()
4. ❌ **Autotask tickets** - Used ON CONFLICT → Removed
5. ❌ **ITGlue assets** - Used update() logic → Changed to create()
6. ❌ **All integrations** - Individual AutoMatchCustomersJob → Batch job

### Result

**All 8 integrations now follow identical pattern:**
- ✅ Always `create()` for time-series data
- ✅ Never `updateOrCreate()` or `ON CONFLICT`
- ✅ Batch customer matching via `BatchAutoMatchCustomersJob`
- ✅ TimescaleDB compression handles efficiency

---

## Common Questions

### Q: Why does database stay small?

**A:** TimescaleDB compression!
- 17K+ records = 64 KB compressed
- Identical data compresses to ~10% original size
- Automatic background compression

### Q: Won't millions of "duplicates" slow down queries?

**A:** No!
- TimescaleDB optimized for time-series queries
- Indexes on timestamp + data_type = fast lookups
- Reports use `->unique()` to show latest only
- Compression doesn't affect query speed

### Q: How do I view historical changes?

**A:** Query by timestamp range:
```sql
SELECT data_timestamp, data_payload->>'status'
FROM integration_data  
WHERE data_payload->>'device_id' = 'abc123'
AND data_timestamp BETWEEN '2025-12-01' AND '2025-12-31'
ORDER BY data_timestamp;
```

### Q: Can I delete old snapshots?

**A:** Yes, but not recommended!
- Time-series data IS the value
- Storage is cheap (120 MB/year)
- Historical analysis requires history
- Consider retention policy (e.g., 2 years)

---

## Monitoring Database Health

### Check Compression Status

```sql
SELECT 
    COUNT(*) as total_chunks,
    COUNT(*) FILTER (WHERE is_compressed) as compressed,
    pg_size_pretty(SUM(total_bytes)) as total_size
FROM timescaledb_information.chunks 
WHERE hypertable_name = 'integration_data';
```

### Check Record Growth

```sql
SELECT 
    integration_id,
    data_type,
    COUNT(*) as records,
    MIN(data_timestamp::date) as oldest,
    MAX(data_timestamp::date) as newest
FROM integration_data
GROUP BY integration_id, data_type
ORDER BY integration_id, records DESC;
```

### Find "Duplicates" (Normal!)

```sql
SELECT 
    data_payload->>'device_id' as device,
    data_type,
    COUNT(*) as snapshots,
    array_agg(DISTINCT data_timestamp::date ORDER BY data_timestamp::date DESC) as dates
FROM integration_data
WHERE integration_id = 2
GROUP BY data_payload->>'device_id', data_type
HAVING COUNT(*) > 5
ORDER BY snapshots DESC
LIMIT 10;
```

---

## Best Practices Summary

✅ **DO:**
- Use `IntegrationData::create()` for all time-series data
- Use `DB::table()->insert()` for batch inserts
- Use `BatchAutoMatchCustomersJob` for customer matching
- Let TimescaleDB handle compression
- Keep historical snapshots for audit trail

❌ **DON'T:**
- Use `updateOrCreate()` on time-series data
- Use `ON CONFLICT` to deduplicate time-series
- Delete "duplicate" snapshots (they're features!)
- Try to manually deduplicate before inserting
- Dispatch individual AutoMatchCustomersJob per customer

---

**Last Updated:** December 15, 2025  
**Applies to:** All integrations (Datto, Autotask, ITGlue, LibreNMS, Sendmarc, SaaS, Barracuda, BitDefender)
