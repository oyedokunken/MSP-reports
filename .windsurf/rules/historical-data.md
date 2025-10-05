---
trigger: always_on
---

## Historical Data System

### Purpose

Store time-series data from integrations to enable trend analysis, historical reporting, and compliance.

### Data Types

- **Events:** Discrete occurrences (backup completed, ticket created, alert triggered)
- **Metrics:** Time-series measurements (uptime percentage, response time, disk usage)
- **State Changes:** Status transitions (device online→offline, ticket open→resolved)

### Storage in TimescaleDB

**IntegrationData Hypertable:**

- Automatically partitioned by data_timestamp (daily chunks)
- Compressed after 7 days (90%+ space savings)
- Indexed for fast queries by customer and data type
- Supports complex JSON payloads for integration-specific data

**Continuous Aggregates:**

- Daily rollups (integration_data_daily) - updated hourly
- Monthly rollups (integration_data_monthly) - updated daily
- Reports query aggregates instead of raw data (10-100x faster)
- Automatically maintained by TimescaleDB

### Historical Import Strategy

**Initial Import on Integration Activation:**

1. User enables integration
2. Integration declares available history range
3. User selects import range (up to company's retention limit)
4. System creates HistoricalImportJob
5. Chunk import into daily segments
6. Priority queue: recent data first, then backfill older
7. Import runs asynchronously in background
8. Progress visible in UI
9. Can pause/resume/cancel

**Chunking Strategy:**

- Newest 7 days: Priority 1 (needed for weekly reports)
- Days 8-30: Priority 2 (needed for monthly reports)
- Days 31-90: Priority 3 (quarterly reports)
- Days 90+: Priority 4 (historical analysis)

**Rate Limiting During Import:**

- Historical imports use fraction of rate limit (50-70%)
- Real-time syncs always have priority
- Import jobs throttle if approaching limit
- Can configure import to run only during off-peak hours

### Aggregation & Compression

**Automatic via TimescaleDB:**

- Continuous aggregates auto-refresh on schedule
- Compression applies to chunks older than threshold
- Retention policies auto-drop expired data

**Benefits:**

- No custom aggregation jobs needed
- Data compressed transparently (queries still work)
- Fast queries on old data
- Automatic cleanup

### Backfill Gap Detection

**Gap Detection:**

- Compare expected data points to actual
- Flag missing periods
- Prioritize backfilling recent gaps over old historical import

**Backfill Process:**

1. Detect gap in data timeline
2. Check if integration can provide data for that period
3. Create backfill job for specific date range
4. Higher priority than initial historical import
5. Log backfill operations for audit