---
trigger: always_on
---

## Integration Framework

### Design Philosophy

Modular, namespace-based system where each integration type is self-contained. Adding a new integration means creating files in a namespace and registering it - no core system changes.

### Integration Contract (Interface)

Every integration must implement:

- **authenticate()** - Validate credentials and establish connection
- **sync()** - Pull latest current-state data
- **getCustomers()** - Return customer list in standardized format
- **getHistoricalData()** - Pull historical data for date range
- **getAvailableHistoryRange()** - Query how far back data is available
- **getMetadata()** - What data fields this integration provides
- **disconnect()** - Clean up connection/revoke tokens
- **healthCheck()** - Test if connection still valid
- **getRateLimits()** - Declare API rate limits

### Integration Registration

Each integration announces itself to the framework:

**Registration includes:**

- Display name and description
- Required credential fields (API key, username/password, OAuth config)
- Optional configuration fields
- Supported data types (what data this integration provides)
- Historical data capabilities (supports historical? how far back?)
- Rate limits (requests per minute/hour, burst allowance)
- Sync frequency recommendations

### Integration Namespace Structure

```
App/Integrations/
├── Contracts/
│   └── IntegrationInterface.php
├── Datto/
│   ├── DattoIntegration.php (implements interface)
│   ├── DattoClient.php (API communication)
│   ├── DattoMapper.php (data transformation)
│   └── config.php (registration config)
└── Autotask/
    ├── AutotaskIntegration.php
    ├── AutotaskClient.php
    ├── AutotaskMapper.php
    └── config.php
```

### Data Standardization Flow

1. Integration pulls data from external API (integration-specific format)
2. Mapper transforms to standardized format
3. Framework validates against schema
4. Stores in IntegrationCustomer (current state) or IntegrationData (historical)
5. Framework triggers customer mapping if new customers found

### Sync Engine (Orchestrates All Integrations)

**Scheduled Sync Process:**

1. Query all active integrations across all companies
2. Check each integration's sync schedule
3. For due syncs, queue sync job
4. Sync job calls integration's sync() method
5. Integration returns data in standardized format
6. Framework stores data
7. Update last sync timestamp and status
8. Trigger customer mapping for new customers
9. Log sync to SyncLog
10. Send notifications on failures

**Error Handling:**

- Retry failed syncs with exponential backoff
- After 3 failures, mark integration as "error" status
- Notify company admins
- Continue syncing other integrations (isolation)

### Historical Data Import Process

**Initiation:**

1. User activates integration
2. Integration declares historical capabilities
3. User chooses import range (up to retention limit)
4. System creates HistoricalImportJob
5. Chunked import jobs queued by priority

**Chunked Import:**

- Break import into daily chunks
- Queue highest priority chunks first (recent data)
- Each chunk job:
  - Check rate limiter
  - Call integration's getHistoricalData() for single day
  - Transform and store in IntegrationData
  - Update progress
  - Queue next chunk
- Can pause/resume at chunk boundaries
- Survives server restarts

**Progress Tracking:**

- HistoricalImportJob updated in real-time
- UI shows progress percentage
- Estimated time to completion
- Can cancel import (stops queuing new chunks)