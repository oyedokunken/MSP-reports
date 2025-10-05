---
trigger: always_on
---

## Multi-Tenancy Architecture

### Core Principle

Complete tenant isolation at every layer. One company must never see, access, or infer another company's data.

### Isolation Mechanisms

**Database Level:**

- Every query scoped to `company_id`
- Global middleware enforces company context
- No cross-company JOINs possible
- Separate encryption keys per company (credential storage)

**Application Level:**

- Company context loaded from authentication
- All Eloquent queries automatically scoped
- User permissions checked against company context
- API keys belong to specific company

**UI Level:**

- White-label branding per company
- Subdomain routing for SaaS (future)
- Company logo, colors, terminology

**Data Level:**

- Reports branded per company
- Email templates use company branding
- No shared data between companies
- Audit logs track all cross-tenant access attempts

### Multi-Tenancy Testing Requirements

- Automated tests attempting cross-tenant data access (must fail)
- Permission escalation tests
- Company context switching tests
- Data leakage detection in logs

---

## Database Architecture

### TimescaleDB Configuration

**Hypertables (Time-Series Data):**

- `integration_data` - raw time-series data from integrations
- Partitioned automatically by day
- Compressed after 7 days (90%+ space reduction)
- Retention policies per company settings

**Continuous Aggregates (Auto-Maintained Views):**

- `integration_data_daily` - daily rollups, refreshed hourly
- `integration_data_monthly` - monthly rollups, refreshed daily
- Reports query these instead of raw data for speed

**Standard Tables:**

- Companies, users, integrations, customers, etc.
- Regular PostgreSQL tables with indexes

### Partitioning Strategy

**By Time (Automatic via TimescaleDB):**

- `integration_data` partitioned by `data_timestamp`
- Chunks created automatically as data arrives
- Old chunks compressed and eventually dropped per retention policy

**By Company (If Needed at Scale):**

- If single company grows massive, can partition their data separately
- Not needed for first deployment

### Encryption

**Volume Encryption:**

- Entire PostgreSQL data directory encrypted at rest
- Key management via host system

**Field-Level Encryption:**

- Integration credentials encrypted in database
- Uses Laravel encryption (AES-256)
- Separate keys per company stored securely