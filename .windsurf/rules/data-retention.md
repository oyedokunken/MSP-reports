---
trigger: always_on
---

## Data Retention & Cleanup

### Retention Policies (Per Company)

**Configurable in Company Settings:**

- Raw events: 90 days default
- Aggregated daily data: 365 days default
- Aggregated monthly data: 1095 days (3 years) default
- Audit logs: 84 months (7 years) - not configurable, compliance requirement
- Generated reports: 1095 days default
- Integration sync logs: 365 days default

**Licensed Retention:**

- Company's license determines maximum retention
- Can set lower than license allows
- Cannot exceed license allowance

### Nightly Cleanup Job

**Schedule:** Runs daily at 2am system time

**Phase 1: Mark for Deletion**

- For each company, read retention settings
- Query records older than retention period
- Soft delete (set deleted_at timestamp)
- Log to DataRetentionLog

**Phase 2: Grace Period Hard Delete**

- Find soft-deleted records > 30 days old
- Permanently delete from database
- Delete associated files (PDFs, etc.)
- Log to AuditLog

**Phase 3: Orphan Cleanup**

- Find files without database records
- Find deleted companies (cancelled subscriptions)
- If deleted company past retention grace period, purge all data
- Log purges

**Phase 4: Aggregate Compression**

- Identify raw data eligible for aggregation
- TimescaleDB compression handles this automatically
- Verify continuous aggregates refreshed

### What Gets Retained

**Forever (Never Deleted):**

- Company records (even after cancellation, keep metadata)
- License records (financial/legal audit)
- Critical audit logs (access, permissions, security events)
- Data breach/security incident logs

**Retention Policy Applies:**

- IntegrationData (time-series historical data)
- GeneratedReports (and associated PDF files)
- SyncLogs
- Most AuditLog entries (except critical)

**Immediate Deletion:**

- Session tokens on logout
- Password reset tokens after use
- Temporary files after processing
- Cache entries on expiry

### Compliance Reporting

**Retention Compliance Report:**

- Shows current data volumes by type
- Shows retention policies applied
- Lists recent purges
- Proves policy enforcement for audits

**Data Subject Request Support:**

- Can identify all data for specific customer
- Can export customer's data (GDPR compliance)
- Can purge specific customer on request (right to be forgotten)
- Audit trail of purge requests