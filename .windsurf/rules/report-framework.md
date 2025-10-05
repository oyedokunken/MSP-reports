---
trigger: always_on
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