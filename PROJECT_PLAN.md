# Multi-Tenant Reporting Platform - Comprehensive Project Plan

## Table of Contents

### Strategic Overview
- [Executive Summary](#executive-summary)
- [Project Purpose & Goals](#project-purpose--goals)
- [Business Model](#business-model)
- [Deployment Strategy](#deployment-strategy)

### Technical Architecture
- [Technology Stack](#technology-stack)
- [Multi-Tenancy Architecture](#multi-tenancy-architecture)
- [Database Architecture](#database-architecture)
- [Security & Compliance](#security--compliance)

### Core Systems
- [Data Models](#data-models)
- [Integration Framework](#integration-framework)
- [Customer Mapping System](#customer-mapping-system)
- [Report Framework](#report-framework)
- [Historical Data System](#historical-data-system)
- [Rate Limiting Framework](#rate-limiting-framework)

### Features & Configuration
- [Scheduling System](#scheduling-system)
- [White Labeling & Branding](#white-labeling--branding)
- [Licensing System](#licensing-system)
- [API Key System](#api-key-system)
- [Data Retention & Cleanup](#data-retention--cleanup)

### Implementation
- [Development Phases](#development-phases)
- [First Customer Deployment](#first-customer-deployment)
- [Migration to SaaS](#migration-to-saas)

### Operations
- [Monitoring & Maintenance](#monitoring--maintenance)
- [Backup & Disaster Recovery](#backup--disaster-recovery)
- [Performance Optimization](#performance-optimization)

---

## Executive Summary

Building a multi-tenant reporting platform that consolidates data from multiple business systems (PSA, RMM, etc.), intelligently maps customers across systems, and generates branded reports on flexible schedules. First deployment is single-tenant on-premise for Territory Technology Solutions, but architecture is fully multi-tenant and scalable to SaaS.

**Key Principles:**
- Multi-tenant from day one, even for single-tenant deployment
- Built for scale and speed using TimescaleDB
- Modular integration and report frameworks
- White-label capable per company
- ISO 27001 compliant with full audit trails
- Historical data support with async backfill
- Flexible licensing model

---

## Project Purpose & Goals

### The Problem
MSPs and telcos manage customer data across fragmented systems - PSA tools, RMM platforms, backup solutions, monitoring systems. Customer names don't match across systems ("ACME Corp" vs "ACME Corporation"). Generating monthly reports requires manual data gathering, compilation, and delivery. This consumes hours per customer, doesn't scale, and inconsistencies damage professional image.

### The Solution
Centralised platform that:
- Integrates with multiple systems via modular framework
- Intelligently maps customers across systems despite naming mismatches
- Automatically generates professional, branded reports on configurable schedules
- Delivers reports via email or API
- Provides historical data analysis and trends
- White-labels completely for each MSP/telco

### Expected Outcomes
- **For Clients:** Save 10-20 hours/month per staff member on report compilation
- **For End Customers:** Professional, consistent, timely reports improve satisfaction
- **For Athena:** Recurring revenue product with per-integration licensing
- **For Industry:** Set standard for telco/MSP reporting in Australia

### Success Metrics
- First customer live and generating reports within 12 weeks
- Reports generating and delivering reliably (99%+ success rate)
- Historical data backfill completes within 48 hours
- Platform handles 100+ customers per company without performance degradation
- Zero data leakage between tenants (ISO 27001 compliance)

---

## Business Model

### Pricing Structure
- **Base Platform:** Licensed per company
- **Integration Modules:** Licensed per integration type (Datto, Autotask, etc.)
- **Data Retention:** Tiered licensing (12 months standard, 24/36 months premium)
- **Users:** Unlimited users per company (differentiate on integrations, not seats)
- **Reports:** Unlimited report types and generation

### First Customer Deployment
- On-premise installation at Territory Technology Solutions
- Unlimited license (all integrations, unlimited retention)
- Acts as proof-of-concept and feature validation
- Provides real-world usage data for optimization

### SaaS Model (Future)
- Same codebase, different deployment
- Enable license enforcement
- Add billing integration (Stripe or similar)
- Subdomain-based white-labeling
- Self-service signup and configuration

---

## Deployment Strategy

### Phase 1: Single Tenant On-Premise (First Customer)
**Timeline:** 8-12 weeks

**Characteristics:**
- Deployed to customer's infrastructure
- Full multi-tenant architecture (but only one tenant)
- Unlimited licensing configured
- Docker Compose deployment
- Direct customer support from Athena

**Objectives:**
- Validate product-market fit
- Refine integration framework with real Datto data
- Test report generation at scale
- Gather feature requests and pain points
- Build operational playbooks

### Phase 2: Multi-Customer On-Premise (2-6 Deployments)
**Timeline:** Months 4-9

**Characteristics:**
- Same deployment model as Phase 1
- Each customer isolated instance
- Begin testing license enforcement
- Standardize deployment procedures
- Build customer onboarding process

**Objectives:**
- Prove repeatability of deployment
- Test different integration combinations
- Validate licensing model
- Build support infrastructure
- Create training materials

### Phase 3: SaaS Launch
**Timeline:** Month 10+

**Characteristics:**
- Centralized hosting on Athena infrastructure
- True multi-tenant with tenant isolation
- Active license enforcement
- Automated billing
- Self-service onboarding

**Objectives:**
- Scale to 50+ companies
- Reduce deployment friction
- Increase revenue per customer
- Build brand recognition

---

## Technology Stack

### Frontend
- **Framework:** React 18+
- **State Management:** React Context or Zustand (lightweight)
- **Styling:** Tailwind CSS (utility-first, white-label friendly)
- **Charts/Graphs:** Apache eCharts
- **HTTP Client:** Axios
- **Build Tool:** Vite

### Backend
- **Framework:** Laravel 12
- **Language:** PHP 8.2+
- **API Pattern:** RESTful with consistent response structure
- **Authentication:** Laravel Sanctum (sessions + API tokens)
- **Validation:** Laravel Form Requests
- **Queue System:** Laravel Queue with Redis driver

### Database
- **Primary:** TimescaleDB (PostgreSQL 16 with time-series extension)
- **Caching:** Redis 7+
- **Why TimescaleDB:** 
  - Native time-series optimization for historical data
  - Automatic partitioning by time
  - Continuous aggregates (auto-updating materialized views)
  - Built-in compression (90%+ reduction for old data)
  - Native retention policies
  - Still PostgreSQL underneath (encryption at rest, Laravel compatible)

### Infrastructure
- **Containerization:** Docker + Docker Compose
- **Web Server:** NGINX or Caddy (TLS termination)
- **Process Management:** Supervisor (queue workers, scheduler)
- **Storage:** Local filesystem initially, S3-compatible for SaaS
- **Backups:** Automated PostgreSQL dumps with encryption

### Security
- **Encryption at Rest:** PostgreSQL/TimescaleDB volume encryption
- **Encryption in Transit:** TLS 1.3 for all API calls
- **Credential Storage:** Encrypted in database using Laravel encryption
- **Password Hashing:** Bcrypt (Laravel default)
- **Session Management:** Redis-backed sessions with configurable timeout

### External Services
- **Email Delivery:** SMTP (configurable per company or system default)
- **PDF Generation:** Gutenberg PDF API
- **File Storage:** Local initially, migrate to object storage for SaaS
- **Monitoring:** Application health checks, integration status tracking

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

---

## Security & Compliance

### ISO 27001 Requirements

**Access Control:**
- Role-based permissions at user and API key level
- Principle of least privilege enforced
- Multi-factor authentication support
- Session timeout based on company policy
- IP whitelisting optional per company

**Audit Logging:**
- Immutable audit trail for all actions
- User logins, permission changes, data access, configuration changes
- API calls logged with actor (user or API key)
- Retention: 7 years minimum for compliance
- Cannot be deleted even by company admins

**Data Protection:**
- Encryption at rest and in transit
- Secure credential storage with per-company keys
- Data retention policies enforced automatically
- Secure deletion when retention expires
- Backup encryption

**Incident Response:**
- Automated alerts for security events
- Failed login tracking and lockout
- Rate limit violations logged
- Suspicious activity detection
- Breach notification procedures

### Compliance Monitoring

**Automated Checks:**
- Daily validation of encryption status
- Retention policy enforcement verification
- Orphaned data detection and cleanup
- Permission audit (users with excessive permissions)
- Integration credential rotation reminders

**Reporting:**
- Compliance dashboard per company
- Audit log export for external audits
- Data retention reports
- Access reports (who accessed what)

---

## Data Models

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

---

## Customer Mapping System

### Purpose
Unify customer records across multiple integrations despite naming inconsistencies. "ACME Corp", "ACME Corporation", "Acme Corp." are all the same customer.

### Auto-Matching Logic

**Algorithm:**
1. New IntegrationCustomer arrives from sync
2. Compare against existing Customers in same company
3. Calculate similarity score (Levenshtein distance, soundex, etc.)
4. If score >= threshold (from company settings, default 85%):
   - Create tentative CustomerMapping
   - If company requires manual review, mark as "pending approval"
   - If no manual review required, auto-approve mapping
5. If score < threshold:
   - Add to "unmapped customers" queue
   - Notify admin

**Confidence Score Factors:**
- Exact match = 100%
- Case-insensitive match = 95%
- Minor punctuation differences = 90%
- Word order changes = 85%
- Abbreviations = 80%
- Below threshold = manual review required

### Manual Mapping Interface

**User Workflow:**
1. View unmapped IntegrationCustomers
2. Search existing Customers
3. See suggested matches with confidence scores
4. Options:
   - Map to existing Customer
   - Create new Customer (truly new customer)
   - Ignore (not a customer, maybe a vendor)

**Bulk Operations:**
- Select multiple unmapped customers
- Apply same mapping rule (e.g., "Corp" → "Corporation")
- Approve all suggested matches above threshold

### Source of Truth Concept

**Designated Integration:**
- Company selects one integration as "source of truth" for customer list
- This integration's customer names become canonical
- Other integrations' customers map TO these

**Behavior:**
- Source of truth integration syncs first
- New customers from source → auto-create Customer record
- Other integrations' new customers → attempt mapping to existing
- If source of truth customer deleted → can cascade or orphan based on policy

**Benefits:**
- Solves "which name is correct?" problem
- Reduces mapping conflicts
- Simplifies onboarding (start with authoritative list)

### Customer Merge/Split Tools

**Merge:**
- Two Customers accidentally created for same real customer
- Merge operation:
  - Choose primary Customer (name, metadata)
  - Move all IntegrationCustomer mappings to primary
  - Move all ReportSchedules to primary
  - Soft-delete duplicate Customer
  - Audit log records merge

**Split:**
- One Customer accidentally mapped to two real customers
- Split operation:
  - Create new Customer
  - Reassign specific IntegrationCustomers to new Customer
  - Clone or reassign ReportSchedules
  - Audit log records split

---

## Report Framework

### Design Philosophy
Same modular approach as integrations. Each report type is self-contained. Adding new report type means creating files in namespace - no core changes.

### Report Contract (Interface)
Every report must implement:
- **getRequiredDataSources()** - Which integrations/data types needed
- **getRequiredHistoricalRange()** - Days of history needed
- **validate()** - Check if enough data exists to generate
- **generate()** - Create the report for specific customer and period
- **getTemplate()** - Return the view/template to use
- **deliver()** - Send via configured method

### Report Registration
Each report announces itself:
- Display name and description
- Required data types (uptime, backups, tickets, etc.)
- Optional data types (enhances report if available)
- Supported frequencies (daily/weekly/monthly/yearly)
- Configuration options (which metrics to include, etc.)
- Output formats supported (PDF, HTML, CSV)

### Report Namespace Structure
```
App/Reports/
├── Contracts/
│   └── ReportInterface.php
├── UptimeReport/
│   ├── UptimeReport.php (implements interface)
│   ├── UptimeDataGatherer.php (pulls data from integrations)
│   ├── template.blade.php (PDF/HTML template)
│   └── config.php (registration)
└── SecuritySummary/
    ├── SecuritySummary.php
    ├── SecurityDataGatherer.php
    ├── template.blade.php
    └── config.php
```

### Report Generation Flow

**Triggered by Schedule:**
1. Schedule engine identifies due reports
2. For each due report:
   - Load Customer and mapped IntegrationCustomers
   - Load report type class
   - Call validate() to check data availability
   - If valid, queue generation job
   - If invalid, log reason and notify admin

**Generation Job:**
1. Gather data from IntegrationData (query time-series)
2. Aggregate if needed (monthly report queries daily rollups)
3. Handle multiple integrations (combine if customer in multiple RMM tools)
4. Apply white-label branding from company settings
5. Apply customer-specific branding overrides if present
6. Render template to PDF/HTML
7. Store in GeneratedReport model
8. Queue delivery job

**Delivery Job:**
1. Load GeneratedReport
2. Apply email template with white-label branding
3. Attach PDF
4. Send via configured method (SMTP, API webhook, etc.)
5. Update delivery status
6. Retry on failure (3 attempts with backoff)
7. Log to audit trail

### Data Gathering for Reports

**Query Strategy:**
- Reports declare required data types
- Framework maps data types to integrations
- Queries IntegrationData filtered by:
  - Company (tenant isolation)
  - Customer (via IntegrationCustomer mapping)
  - Data type
  - Time range
- Returns aggregated or raw data as needed

**Handling Missing Data:**
- Report validates data availability before generation
- If critical data missing:
  - Option 1: Skip generation, notify admin
  - Option 2: Generate partial report with "data unavailable" sections
  - Option 3: Queue historical import if data should exist
- Company configures preferred behavior

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

---

## Rate Limiting Framework

### Purpose
Respect external API rate limits to prevent bans, throttling, or service degradation.

### Per-Integration Rate Limit Configuration

Each integration declares its limits:
```
{
  "requests_per_minute": 60,
  "requests_per_hour": 1000,
  "burst_allowance": 10,
  "min_request_interval_ms": 100,
  "concurrent_requests": 3,
  "historical_import_multiplier": 0.5
}
```

Different integration types have different limits (Datto vs Autotask vs custom APIs).

### Rate Limiter Service

**Tracking (Redis-based for speed):**
- Requests per minute counter (expires after 60s)
- Requests per hour counter (expires after 3600s)
- Last request timestamp
- Active concurrent requests count

**Per Integration Instance:**
- Each Integration model has its own rate limit tracking
- Company A's Datto integration tracked separately from Company B's
- Prevents one company's heavy usage affecting others

### Enforcement Points

**Before Every API Call:**
1. Check if under rate limit
2. If yes, proceed immediately
3. If no, queue request or wait for slot
4. Track request in counters
5. Make API call
6. Release slot when complete

**Smart Throttling:**
- Calculate optimal wait time based on bucket resets
- Respect minimum interval between requests
- Handle burst allowances
- Back off if API returns rate limit errors

### Priority System

**Real-Time Syncs:** Highest priority, use full rate limit
**Backfill Jobs:** Medium priority, pause if real-time sync needed
**Historical Imports:** Lowest priority, use only percentage of limit

**Dynamic Adjustment:**
- If approaching limit, slow down low-priority jobs
- If API returns "rate limit exceeded", pause all for reset period
- If plenty of quota available, speed up imports

### Multi-Company Coordination

**Problem:** 50 companies all using Datto, sharing Athena's IP
- Datto might rate limit by IP, not API key
- One company's heavy usage affects others

**Solution (SaaS Future):**
- Global rate limiter tracks across all companies for same API
- Distributes quota fairly or by subscription tier
- Premium tiers get higher allocation

**First Customer (On-Premise):**
- No cross-company coordination needed
- Simple per-integration rate limiting sufficient

### Monitoring & Alerts

**Track:**
- Rate limit hits (approaching/exceeded)
- API errors indicating throttling
- Jobs delayed due to rate limiting
- Average wait times

**Alert:**
- Company admin if sync delayed > 2 hours due to rate limits
- Athena team if consistent rate limit issues
- Suggest integration quota upgrade if chronic issue

---

## Scheduling System

### Flexible Schedule Configuration

**Supported Frequencies:**
- Daily (every day or specific days of week)
- Weekly (specific day of week)
- Monthly (specific day of month, with fallback for months without that day)
- Yearly (specific month and day)
- On-demand (manual trigger only)

**Time of Day:**
- Specified in company's timezone
- HH:MM format (24-hour)
- Schedule engine converts to UTC for processing

**Configuration Examples:**
See ReportSchedule model in Data Models section for JSON structure.

### Schedule Evaluation Engine

**Runs Hourly (or more frequently):**
1. Query all active ReportSchedules
2. Convert schedule times to UTC
3. Compare next_scheduled_timestamp to current time
4. Queue due reports for generation
5. Calculate and update next_scheduled_timestamp
6. Handle timezone changes (DST transitions)

**Next Schedule Calculation:**
- Daily: Add 24 hours, skip if not in days_of_week
- Weekly: Add 7 days
- Monthly: Same day next month, fallback to last day if needed
- Yearly: Same date next year

### Missed Schedule Handling

**Scenarios:**
- Server down during scheduled time
- Queue backed up
- Report generation failed

**Policies (Company Configurable):**
- Generate all missed reports (catch up)
- Generate only most recent missed (skip old)
- Skip all missed (just resume schedule)

**Implementation:**
- Track missed generations in audit log
- Notify admin of missed schedules
- Allow manual trigger to generate missed report

### Schedule Preview

**UI Feature:**
Users can preview next N scheduled generation times:
- "Next 5 Reports: Dec 1 9am, Jan 1 9am, Feb 1 9am..."
- Validates schedule configuration makes sense
- Shows impact of timezone settings

### Multiple Schedules Per Customer

A customer can have:
- Daily backup summary (Monday-Friday 9am)
- Weekly security report (Monday 9am)
- Monthly comprehensive report (1st of month 9am)

Each is separate ReportSchedule record.

---

## White Labeling & Branding

### Multi-Level Branding System

**Level 1: Company (MSP/Telco) Branding**
- Applied to all UI, emails, reports for that company's users
- Stored in Company settings JSON
- Includes: logos, colors, contact info, email templates

**Level 2: Customer-Specific Overrides**
- Applied to reports delivered to specific customer
- Stored in Customer metadata JSON
- Includes: customer logo, custom report title, footer notes
- Optional - defaults to company branding

### UI White-Labeling

**Frontend:**
- React app loads branding from API on initialization
- CSS variables set dynamically (primary color, secondary color)
- Logo loaded into header
- Favicon set dynamically
- Page titles use company name

**Authentication Pages:**
- Login page fully branded
- Password reset emails use company branding
- MFA setup screens branded

### Report Branding

**PDF/HTML Templates:**
- Header: Company logo + customer logo (if provided)
- Colors: Company's primary/secondary colors for charts/accents
- Footer: Company contact info + customer-specific notes
- Watermark: Subtle company branding

**Email Delivery:**
- Email template uses company branding
- From address: Company's email
- From name: Company's name
- Email body text uses company colors
- Footer includes company support contact

### Configuration Fields

See Company settings JSON in Data Models section for complete white_label structure.

### On-Premise Considerations

**First Customer:**
- Set white-label config during deployment
- Can update via UI if needed
- All assets (logos, favicons) stored locally or in their storage

**SaaS Future:**
- Subdomain routing: customermsp.reportingplatform.com.au
- Subdomain determines which company branding to load
- Asset storage in object storage with company-namespaced paths

---

## Licensing System

### License Types

**Integration Licenses:**
- Per integration type (Datto, Autotask, Kaseya, etc.)
- Controls which integrations can be activated
- SKU example: "integration_datto", "integration_autotask"

**Retention Licenses:**
- Controls historical data retention period
- SKU example: "retention_12_months", "retention_36_months", "retention_unlimited"

**User Licenses (Future):**
- Per-seat licensing if business model changes
- Currently unlimited users per company

**Feature Licenses (Future):**
- Advanced report types
- API access levels
- Custom integrations

### License Enforcement

**Check Points:**
1. **Integration Activation:** Verify license exists for integration type
2. **Historical Import:** Verify retention license allows import range
3. **Report Generation:** Verify license for report type (if premium reports added)
4. **User Creation:** Verify user license quota (if per-seat model)

**Enforcement Behavior:**
- If license valid: Allow action
- If license expired: Grace period (7 days?) then block
- If license missing: Show "Contact sales" message in UI
- If license insufficient: Offer upgrade option

**First Customer Configuration:**
- License SKU: "unlimited"
- Expiry date: null (perpetual)
- Quantity: unlimited
- All checks return "allowed"

**SaaS Configuration:**
- Specific licenses per company
- Expiry dates enforced
- Integration with billing system
- Automatic license expiry notifications

### License Model

See License Model in Data Models section for attributes and relationships.

### License Management UI

**For Company Admins:**
- View current licenses
- See expiry dates
- Request license additions/upgrades
- View license usage (e.g., 3 of 5 integrations activated)

**For Athena (SaaS):**
- Admin panel to manage all company licenses
- Bulk license operations
- License renewal workflows
- Usage analytics (which integrations most popular)

---

## API Key System

### Purpose
Allow programmatic access to platform with same granular permissions as human users.

### API Key Model

See ApiKey Model in Data Models section for full structure.

### Permission System

**Same Structure as User Permissions:**
- Integrations: view/manage/sync
- Customers: view/edit/map
- Reports: view/configure/generate/download
- Users: view/manage (typically false for API keys)
- Company settings: view/edit (typically false for API keys)

**Use Cases:**
- **Report Generation Key:** Can trigger report generation and download results, can't modify configurations
- **Sync-Only Key:** Can trigger integration syncs, can't view customer data
- **Read-Only Key:** Can query reports and customer data, can't modify anything
- **Webhook Key:** Can receive webhooks and update specific data, restricted scope

### API Key Generation

**Process:**
1. User with "manage users" permission creates API key
2. Sets display name: "Production Reporting API"
3. Configures permissions (checkboxes like user permissions)
4. Optional: IP whitelist, endpoint restrictions
5. System generates cryptographically secure key (64+ chars)
6. Displays key ONCE (user must copy immediately)
7. Stores bcrypt hash only
8. Key lost? Must revoke and create new one

### Authentication Flow

**API Request with Key:**
```
Authorization: Bearer {api_key}
```

**Middleware:**
1. Extract API key from header
2. Hash and lookup in database
3. Load associated Company
4. Check if key revoked
5. Check if IP allowed (if whitelist configured)
6. Load key's permissions
7. Scope all queries to company
8. Validate requested action against permissions
9. Log request to AuditLog with ApiKey as actor

### Rate Limiting

**Separate from User Sessions:**
- API keys have own rate limit pool
- Typically lower than UI users (prevent abuse)
- Per-key tracking
- Per-endpoint limits (more restrictive on write operations)

**Rate Limit Headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 47
X-RateLimit-Reset: 1640000000
```

### API Key Management

**Revocation:**
- User can revoke key anytime
- Immediate effect (Redis cache cleared)
- Audit log records who revoked when
- Can include revocation reason

**Rotation:**
- No automatic rotation (user controls)
- UI shows "Created 6 months ago - consider rotating"
- Best practice: rotate every 6-12 months

**Monitoring:**
- Last used timestamp visible in UI
- Unused keys flagged (security risk)
- Usage statistics (requests per day/week)

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

---

## Development Phases

### Phase 1: Foundation

**Objectives:**
- Multi-tenant architecture established
- Authentication and authorization working
- Database migrations complete
- White-labeling system functional

**Deliverables:**
- Company model with settings JSON
- User model with granular permissions
- API key system functional
- License checking (always returns true for first customer)
- Login/logout with local authentication
- UI loads white-label branding from API
- PostgreSQL/TimescaleDB running with encryption
- Laravel backend with tenant-scoped queries
- React frontend with Tailwind styling

**Not Included Yet:**
- Integrations (comes next phase)
- Reports (comes after integrations)
- Historical data (comes with integrations)

---

### Phase 2: Integration Framework & Autotask

**Objectives:**
- Integration framework contracts defined
- Autotask integration adapted (already built for another project, just needs to be adapted) and functional
- Current-state syncs working
- Historical data architecture built
- Rate limiting implemented

**Deliverables:**
- Integration contract interface
- Integration registration system
- IntegrationCustomer model and storage
- Autotask integration namespace with all Autotask products
- Sync engine scheduling and executing syncs
- SyncLog tracking
- IntegrationData hypertable (TimescaleDB)
- Historical import job system with chunking
- Rate limiter service with per-integration limits
- Progress tracking for historical imports
- UI for integration management (add/configure/sync)

**Success Criteria:**
- Can connect to first customer's Autotask instance
- Current sync pulls customer list successfully
- Historical import begins and makes progress
- No API rate limit violations
- Data visible in UI

---

### Phase 3: Customer Mapping

**Objectives:**
- Auto-matching algorithm working
- Manual mapping UI functional
- Source of truth integration designated

**Deliverables:**
- Customer model
- CustomerMapping model
- Auto-match algorithm with fuzzy matching
- Manual mapping UI showing unmapped customers
- Customer search and suggestion interface
- Bulk mapping operations
- Source of truth integration setting
- Customer merge/split tools

**Success Criteria:**
- Datto customers auto-map with >80% accuracy
- Remaining customers easy to map manually
- No duplicate customer records
- Mappings auditable in logs

---

### Phase 4: Report Framework & First Report

**Objectives:**
- Report framework contracts defined
- First report type implemented (customer's priority report)
- Report generation working with white-label branding
- Scheduling system functional

**Deliverables:**
- Report contract interface
- Report registration system
- ReportSchedule model with flexible scheduling
- GeneratedReport model and storage
- First report type (e.g., Monthly Uptime Report)
- PDF generation from template
- White-label branding in reports
- Customer-specific branding overrides
- Report generation engine
- Schedule evaluation engine (hourly check)
- TimescaleDB continuous aggregates for report queries

**Success Criteria:**
- Can generate first report type for mapped customer
- Report contains real data from Autotask
- Report branded correctly with customer's white-label settings
- PDF quality professional and readable
- Schedule creates and calculates next run correctly

---

### Phase 5: Report Delivery & Email

**Objectives:**
- Reports automatically deliver on schedule
- Email templates branded
- Delivery tracking working

**Deliverables:**
- Email delivery system with SMTP
- Email templates with white-label branding
- Delivery job queue and processing
- Delivery status tracking
- Failed delivery retry logic
- Delivery notifications to customer
- Email configuration in company settings

**Success Criteria:**
- Reports generate on schedule automatically
- Emails deliver successfully with branding
- Failed deliveries retry and notify
- Customers receive professional-looking report emails

---

### Phase 6: Data Retention & Compliance

**Objectives:**
- Retention policies enforced
- Cleanup automation working
- Compliance reporting available

**Deliverables:**
- TimescaleDB retention policies configured
- Nightly cleanup job (soft delete, hard delete, orphan cleanup)
- DataRetentionLog model and tracking
- Compliance dashboard showing retention status
- Purge notifications to admins

**Success Criteria:**
- Old data automatically purged per company settings
- Audit logs retained for 7 years
- No manual cleanup needed
- Compliance reports available for audit

---

### Phase 7: Polish & Production Prep

**Objectives:**
- UI/UX refined based on feedback
- Performance optimized
- Security hardened
- Documentation complete

**Deliverables:**
- UI polish and user feedback incorporation
- Query optimization (indexes, caching)
- Security audit and fixes
- Load testing (100+ customers, concurrent syncs)
- User documentation (admin guide, user manual)
- Deployment documentation
- Backup and restore procedures tested
- Monitoring and alerting configured
- Training materials for first customer

**Success Criteria:**
- UI intuitive and polished
- Performance acceptable under load
- No critical security issues
- First customer trained and ready to go live
- Deployment repeatable with documentation

---

### Phase 8: First Customer Go-Live

**Objectives:**
- Deploy to first customer's infrastructure
- Complete historical import
- Generate first production reports
- Validate success

**Deliverables:**
- On-premise deployment to customer servers
- Company record created with unlimited license
- Datto integration connected and synced
- Historical data import completed (or in progress)
- Customer mapping completed
- First reports generated and delivered
- Customer staff trained
- Support procedures established

**Success Criteria:**
- Platform running stable in production
- Reports generating and delivering successfully
- Customer satisfied with initial results
- No critical bugs or issues
- Support tickets minimal

---

## First Customer Deployment

### Pre-Deployment

**Infrastructure Requirements:**
- Docker and Docker Compose installed
- Adequate storage (estimate 10GB + 1GB/month growth)
- PostgreSQL volume encryption configured
- TLS certificate for domain
- SMTP credentials for email delivery
- Backup storage configured

**Configuration Preparation:**
- White-label settings gathered (logo, colors, contact info)
- Autotask API credentials obtained
- Customer list exported for mapping validation
- Report requirements documented
- Schedule requirements documented

### Deployment Process

**Step 1: Server Setup**
- Provision server (on-premise at customer location)
- Install Docker and dependencies
- Configure firewall rules
- Set up TLS certificate

**Step 2: Application Deployment**
- Clone repository or deploy release package
- Configure environment variables
- Start Docker Compose stack
- Verify services healthy

**Step 3: Database Initialization**
- Run migrations (creates all tables and hypertables)
- Seed first company record with unlimited license
- Create first admin user
- Configure white-label settings

**Step 4: Integration Setup**
- Add Autotask integration via UI
- Enter Autotask API credentials
- Test connection (health check)
- Initiate first current-state sync
- Verify customer data importing

**Step 5: Historical Import**
- Configure historical import range (90 days recommended initially)
- Start historical import job
- Monitor progress (will run async over 24-48 hours)
- Verify data appearing in database

**Step 6: Customer Mapping**
- Review auto-matched customers (approve or adjust)
- Manually map unmapped customers
- Designate Autotask as source of truth if appropriate
- Verify all customers mapped

**Step 7: Report Configuration**
- Add first report type configuration
- Create report schedules for customers
- Configure delivery destinations
- Test generate one report manually
- Verify PDF quality and branding

**Step 8: Schedule Testing**
- Set test schedule (near-term)
- Wait for schedule trigger
- Verify report generates automatically
- Verify email delivers
- Confirm customer receives professional report

**Step 9: Training**
- Train customer admins on platform
- Demonstrate integration management
- Show customer mapping process
- Explain report configuration
- Document common tasks

**Step 10: Go-Live**
- Enable production schedules
- Monitor first week closely
- Respond to feedback quickly
- Document lessons learned

### Post-Deployment

**Week 1-2 Monitoring:**
- Daily check of sync status
- Daily check of report generation success
- Review error logs
- Check historical import progress
- Respond to customer questions

**Week 3-4 Optimization:**
- Tune sync schedules based on usage patterns
- Optimize slow queries if needed
- Adjust rate limiting if issues
- Refine customer mappings
- Adjust report templates based on feedback

**Ongoing Support:**
- Weekly health checks
- Monthly data retention review
- Quarterly platform updates
- Continuous feature requests and improvements

---

## Migration to SaaS

### When to Migrate
- 3+ successful on-premise deployments
- Proven stability and reliability
- Standardized deployment process
- Support infrastructure established
- Positive customer feedback

### Preparation

**Infrastructure:**
- Cloud hosting provider selected (AWS, Azure, DigitalOcean)
- Multi-region deployment planned (AU region for latency)
- Load balancer configured
- Auto-scaling policies defined
- Backup and DR procedures tested

**Application Changes:**
- Enable license enforcement (flip config flag)
- Add billing integration (Stripe)
- Implement signup flow
- Add company management admin panel
- Subdomain routing for white-labeling
- Multi-tenant monitoring dashboard

**Data Migration:**
- For existing on-premise customers who want to migrate:
  - Export data from on-premise
  - Import to SaaS tenant
  - Verify data integrity
  - Cutover DNS/configuration
  - Maintain on-premise as backup temporarily

### SaaS-Specific Features

**Self-Service Signup:**
- Registration flow
- Email verification
- Company profile setup
- White-label configuration wizard
- Free trial period (14-30 days)

**Billing System:**
- Stripe integration
- Subscription management
- License purchasing flow
- Usage tracking
- Invoice generation

**Admin Panel (for Athena):**
- View all companies
- Manage licenses
- Monitor system health
- Support ticket system
- Usage analytics

**Multi-Tenant Optimizations:**
- Database connection pooling
- Query optimization for scale
- Caching strategy (Redis)
- Background job prioritization
- Resource quotas per company

---

## Monitoring & Maintenance

### Application Monitoring

**Health Checks:**
- API endpoint health (200 OK)
- Database connectivity
- Redis connectivity
- Queue worker status
- Disk space available

**Integration Monitoring:**
- Sync success rates per integration
- API rate limit usage
- Historical import progress
- Error rates and types
- Response times

**Report Monitoring:**
- Generation success rates
- Delivery success rates
- Generation time (performance)
- Failed reports and reasons
- Schedule adherence

**System Monitoring:**
- CPU/memory usage
- Disk I/O
- Network bandwidth
- PostgreSQL query performance
- Queue depth and processing rate

### Alerting

**Critical Alerts (Immediate):**
- Database connectivity lost
- Disk space <10%
- All integrations failing
- Report generation failing for >6 hours
- Security events (unauthorized access attempts)

**Warning Alerts (Review within 4 hours):**
- Integration sync failures (single integration)
- Rate limit repeatedly hit
- Queue depth growing
- Report delivery failures
- Slow query performance

**Info Alerts (Review daily):**
- Historical import completed
- Retention cleanup executed
- New customer signup (SaaS)
- License expiring soon

### Maintenance Tasks

**Daily:**
- Review error logs
- Check sync statuses
- Verify report deliveries
- Monitor disk usage

**Weekly:**
- Review performance metrics
- Check for slow queries
- Review security logs
- Customer feedback review

**Monthly:**
- Database maintenance (VACUUM, REINDEX)
- Backup restoration test
- Security updates
- Dependency updates
- Capacity planning review

**Quarterly:**
- Comprehensive security audit
- Performance optimization
- Feature prioritization
- Customer satisfaction survey

---

## Backup & Disaster Recovery

### Backup Strategy

**Database Backups:**
- Automated daily full backups
- Encrypted at rest
- Retained per company policy (30 days minimum)
- Stored off-site from primary database
- Tested monthly (restore to staging)

**File Backups:**
- Generated reports (PDFs)
- Uploaded logos and assets
- Configuration files
- Same schedule as database

**Application Backups:**
- Docker images tagged and versioned
- Configuration files in version control
- Infrastructure as code (Terraform/CloudFormation)

### Disaster Recovery

**Scenarios Planned For:**
- Database corruption
- Server failure
- Data center outage
- Ransomware/security breach
- Accidental data deletion

**Recovery Procedures:**

**RTO (Recovery Time Objective):** 4 hours
**RPO (Recovery Point Objective):** 24 hours (daily backups)

**Recovery Steps:**
1. Assess incident and scope
2. Notify customers of issue
3. Deploy backup infrastructure
4. Restore database from latest backup
5. Restore file storage
6. Verify data integrity
7. Resume operations
8. Post-mortem and improvements

### High Availability (SaaS Future)

**Architecture:**
- Multi-AZ database (PostgreSQL replication)
- Load-balanced application servers
- Redis replication for cache/queues
- Automated failover
- Health check-based routing

**Target Uptime:** 99.9% (8.76 hours downtime/year)

---

## Performance Optimization

### Database Optimization

**Indexes:**
- Compound indexes on common query patterns
- Partial indexes for frequently filtered subsets
- Regular index maintenance (REINDEX)

**Query Optimization:**
- TimescaleDB chunk pruning (automatic)
- Continuous aggregates for common report queries
- Query caching for expensive operations (5-15 min TTL)
- Explain analyze on slow queries
- Connection pooling

**Partitioning:**
- IntegrationData partitioned by time (automatic via TimescaleDB)
- Partition pruning on queries
- Compression on old partitions

### Application Optimization

**Caching Strategy:**
- Redis for session storage
- Cache report data for 5-15 minutes
- Cache company white-label settings
- Cache integration metadata
- Aggressive cache invalidation on updates

**Queue Management:**
- Separate queues by priority
- Scale workers based on queue depth
- Job timeout policies
- Failed job retry policies

**Asset Optimization:**
- Minified JavaScript/CSS
- Image optimization and CDN (SaaS)
- Lazy loading for large datasets
- Pagination on all list views

### Scaling Strategy

**Vertical Scaling (First):**
- Increase server resources as needed
- Sufficient for 10-20 companies

**Horizontal Scaling (SaaS):**
- Application servers behind load balancer
- Database read replicas for reporting
- Separate queue workers on dedicated instances
- Object storage for files (S3)

---

**End of Project Plan**

---

**Next Steps:**
1. Review and approve this plan with stakeholders
2. Set up development environment
3. Begin Phase 1: Foundation
4. Establish regular check-ins with first customer
5. Create project tracking board (issues, milestones)
6. Schedule architecture review after Phase 1 completion