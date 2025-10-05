---
trigger: always_on
---

## Data Models - this is not an exhaustive list of all models but these do define the base models

### Company Model

**Purpose:** Root tenant entity. Everything belongs to a company.

**Core Attributes:**

- Name, ABN, contact details
- Status: active/suspended/trial
- Subscription tier
- Created/updated timestamps
- Settings JSON (detailed below)

**Relationships:**

- Has many Users
- Has many Integrations
- Has many Customers
- Has many Licenses
- Has many ApiKeys
- Has many AuditLogs

**Settings Array Structure:**

```
{
  "authentication": {
    "auth_methods": ["local", "m365", "saml", "ldap"],
    "saml_config": {...},
    "m365_config": {...},
    "ldap_config": {...},
    "password_policy": {...},
    "require_2fa": false,
    "session_timeout_minutes": 60
  },
  "integrations": {
    "source_of_truth_integration_id": null,
    "auto_sync_enabled": true,
    "sync_schedule": "0 */4 * * *",
    "sync_failure_notifications": ["admin@company.com"]
  },
  "customer_mapping": {
    "auto_match_enabled": true,
    "auto_match_threshold": 85,
    "manual_match_required": false
  },
  "reports": {
    "default_report_destination": "admin@company.com",
    "report_timezone": "Australia/Brisbane",
    "report_retention_days": 1095,
    "report_format": {
      "default_format": "pdf",
      "pdf_page_size": "A4",
      "include_raw_data": false,
      "compress_attachments": true
    }
  },
  "white_label": {
    "enabled": true,
    "company_name": "Customer MSP Name",
    "logo_url": "https://storage/logo.png",
    "primary_color": "#1a56db",
    "secondary_color": "#7c3aed",
    "favicon_url": "https://storage/favicon.ico",
    "email_from_name": "Customer MSP",
    "email_from_address": "reports@customermsp.com.au",
    "support_email": "support@customermsp.com.au",
    "support_phone": "1300 XXX XXX",
    "footer_text": "© 2025 Customer MSP",
    "report_header_text": "Monthly Service Report"
  },
  "data_retention": {
    "default_months": 36,
    "audit_logs_months": 84,
    "generated_reports_months": 36,
    "integration_sync_logs_months": 12,
    "historical_data": {
      "raw_events_days": 90,
      "aggregated_daily_days": 365,
      "aggregated_monthly_days": 1095
    }
  },
  "security": {
    "allowed_ip_ranges": [],
    "encryption_key_id": "company_123_key"
  },
  "licensing": {
    "deployment_type": "on_premise",
    "license_enforcement": false
  },
  "historical_data": {
    "import_on_activation": true,
    "default_import_range_days": 90,
    "aggregation_enabled": true
  }
}
```

---

### User Model

**Purpose:** Human users accessing the platform.

**Core Attributes:**

- Name, email, password hash
- Status: active/inactive/locked
- Last login timestamp
- Auth method used: local/saml/m365/ldap
- Permissions JSON

**Permissions Structure:**

```
{
  "integrations": {"view": true, "manage": true, "sync": true},
  "customers": {"view": true, "edit": true, "map": true},
  "reports": {"view": true, "configure": true, "generate": true, "download": true},
  "users": {"view": true, "manage": true},
  "company_settings": {"view": true, "edit": true}
}
```

**Relationships:**

- Belongs to Company
- Has many AuditLogs

---

### Integration Model

**Purpose:** Configured instance of an integration type (e.g., this company's Datto connection).

**Core Attributes:**

- Integration type (references namespace: "Datto", "Autotask", etc.)
- Display name (user-friendly name for this instance)
- Status: active/disconnected/error/importing_historical
- Last sync timestamp
- Last sync status: success/failed/partial
- Error message
- Credentials (encrypted JSON)
- Configuration (integration-specific settings JSON)
- Is source of truth (boolean)
- Historical import status: pending/running/completed/failed
- Historical import progress (percentage)
- Last historical sync timestamp

**Relationships:**

- Belongs to Company
- Has many IntegrationCustomers
- Has many SyncLogs
- Has many HistoricalImportJobs
- Has many IntegrationData records

**License Check:** Before activation, validates company has license for this integration type.

---

### IntegrationCustomer Model

**Purpose:** Raw customer record as it exists in external integration (before mapping).

**Core Attributes:**

- External ID (customer ID in external system)
- Customer name (as it appears in external system)
- Customer metadata (JSON - everything else from external system)
- Active status
- Last seen timestamp

**Relationships:**

- Belongs to Integration
- Belongs to Customer (through CustomerMapping)

---

### Customer Model

**Purpose:** Unified customer record across all integrations.

**Core Attributes:**

- Canonical name (the "correct" name for this customer)
- Status: active/inactive
- Report configuration JSON (which reports to generate)
- Report destination (email or other delivery method)
- Metadata JSON (custom fields company wants to track)

**Report Configuration Structure:**

```
{
  "enabled_reports": [
    {
      "report_type": "uptime_monthly",
      "schedule_id": 123
    }
  ],
  "report_branding": {
    "customer_logo_url": "https://storage/customer-logo.png",
    "report_title": "ACME Corp - Monthly Report",
    "footer_notes": "Prepared exclusively for ACME Corp"
  },
  "delivery": {
    "method": "email",
    "destination": "reports@acmecorp.com.au",
    "cc": ["manager@acmecorp.com.au"]
  }
}
```

**Relationships:**

- Belongs to Company
- Has many IntegrationCustomers (through CustomerMapping)
- Has many ReportSchedules
- Has many GeneratedReports

---

### CustomerMapping Model

**Purpose:** Links IntegrationCustomers to unified Customer. Audit trail for mappings.

**Core Attributes:**

- Mapping type: auto/manual
- Confidence score (for auto-matches, 0-100)
- Mapped by user ID (for manual matches)
- Mapped at timestamp
- Notes (why this mapping was made)

**Relationships:**

- Belongs to Customer
- Belongs to IntegrationCustomer

---

### IntegrationData Model (TimescaleDB Hypertable)

**Purpose:** Time-series historical data from integrations.

**Core Attributes:**

- Company ID (for tenant isolation)
- Integration ID
- Integration customer ID
- Data type: uptime_metric/backup_status/ticket_event/security_alert/etc.
- Data timestamp (when this event occurred - partition key)
- Data payload (JSONB with actual data)
- Imported at (when we pulled this data)
- Data version (handle schema changes)

**Indexes:**

- Composite: (company_id, data_type, data_timestamp)
- Composite: (integration_customer_id, data_type, data_timestamp)

**TimescaleDB Features:**

- Auto-partitioned by data_timestamp (daily chunks)
- Compressed after 7 days
- Retention policies per company settings

---

### ReportSchedule Model

**Purpose:** Defines when reports generate for which customers.

**Core Attributes:**

- Report type (namespace reference)
- Schedule frequency: daily/weekly/monthly/yearly/on-demand
- Schedule config JSON (detailed below)
- Time of day (HH:MM in company timezone)
- Active status
- Last generated timestamp
- Next scheduled timestamp (calculated)
- Timezone (inherits from company if not overridden)

**Schedule Configuration Examples:**

```
// Daily at 9am Mon-Fri
{
  "frequency": "daily",
  "time": "09:00",
  "days_of_week": [1,2,3,4,5]
}

// Weekly on Monday at 9am
{
  "frequency": "weekly",
  "time": "09:00",
  "day_of_week": 1
}

// Monthly on 1st at 9am
{
  "frequency": "monthly",
  "time": "09:00",
  "day_of_month": 1,
  "fallback_to_last_day": true
}

// Yearly on July 1st at 9am
{
  "frequency": "yearly",
  "time": "09:00",
  "month": 7,
  "day_of_month": 1
}
```

**Relationships:**

- Belongs to Company
- Belongs to Customer
- Has many GeneratedReports

---

### GeneratedReport Model

**Purpose:** Tracks each instance of a generated report.

**Core Attributes:**

- Generation timestamp
- Report period (which date range the report covers)
- Status: pending/generating/completed/failed
- File path (where PDF/HTML stored)
- File size
- Delivered status
- Delivered timestamp
- Delivery method: email/api/download
- Error message (if generation or delivery failed)
- Retention until (when to delete based on company policy)

**Relationships:**

- Belongs to ReportSchedule
- Belongs to Customer
- Belongs to Company

---

### License Model

**Purpose:** Gates feature access per company.

**Core Attributes:**

- License type: integration/retention/users/reports
- Product SKU: "integration_datto", "retention_36_months", "unlimited"
- Status: active/expired/suspended
- Expiry date (null for perpetual)
- Quantity (for user licenses)
- Metadata JSON (license-specific config)

**Relationships:**

- Belongs to Company

**Enforcement:**

- Checked before integration activation
- Checked before extending retention
- First customer: SKU "unlimited", expiry null
- SaaS customers: specific SKUs with expiry dates

---

### ApiKey Model

**Purpose:** Programmatic access with same permissions as users.

**Core Attributes:**

- Key hash (never store plaintext)
- Display name: "Production Reporting System"
- Last used timestamp
- Created by user ID (audit trail)
- Permissions JSON (same structure as User)
- IP whitelist (optional array)
- Allowed endpoints (optional array - restrict to specific routes)
- Revoked status
- Revoked at timestamp
- Revoked by user ID

**Relationships:**

- Belongs to Company
- Has many AuditLogs (polymorphic - actor can be User or ApiKey)

**Rate Limiting:**

- Separate rate limits from user sessions
- Per-key limits based on company tier
- Per-endpoint limits

---

### AuditLog Model

**Purpose:** Immutable record of all actions for ISO 27001 compliance.

**Core Attributes:**

- Event type: login/integration_sync/report_generated/permission_change/etc.
- Actor type: User/ApiKey/System
- Actor ID
- Target type: Integration/Customer/Report/User/etc.
- Target ID
- Event data (JSON - what changed)
- IP address
- User agent
- Timestamp

**Relationships:**

- Belongs to Company
- Polymorphic belongs to Actor (User/ApiKey)

**Retention:** 7 years minimum regardless of company settings.

---

### HistoricalImportJob Model

**Purpose:** Tracks async historical data imports.

**Core Attributes:**

- Integration ID
- Import range start/end dates
- Current position (which date we're up to)
- Status: queued/running/paused/completed/failed
- Total records to import (estimate)
- Records imported so far
- Progress percentage
- Error count
- Priority level (1-4, newer data = higher priority)
- Started at / completed at timestamps

**Relationships:**

- Belongs to Integration
- Belongs to Company

---

### SyncLog Model

**Purpose:** Tracks integration sync operations.

**Core Attributes:**

- Integration ID
- Sync type: current_state/historical/backfill
- Started at / completed at timestamps
- Status: running/success/failed/partial
- Records synced count
- Records failed count
- Error messages (JSON array)
- Rate limit hits count

**Relationships:**

- Belongs to Integration

---

### DataRetentionLog Model

**Purpose:** Tracks data purges for compliance.

**Core Attributes:**

- Company ID
- Record type: Report/IntegrationData/SyncLog/etc.
- Records purged count
- Retention policy used (JSON)
- Purged at timestamp

**Relationships:**

- Belongs to Company