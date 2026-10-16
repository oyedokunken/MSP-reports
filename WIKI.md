# Multi-Tenant Reporting Platform Wiki

> Comprehensive documentation for the MSP/Telco Reporting Platform

## Table of Contents

1. [Getting Started](#getting-started)
2. [Architecture Overview](#architecture-overview)
3. [User Guide](#user-guide)
4. [Admin Guide](#admin-guide)
5. [Integration Guide](#integration-guide)
6. [Report Configuration](#report-configuration)
7. [API Reference](#api-reference)
8. [Troubleshooting](#troubleshooting)
9. [Security](#security)
10. [FAQ](#faq)

---

## Getting Started

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.2+ | 8.3+ |
| Node.js | 18+ | 20+ |
| PostgreSQL | 16+ | 16+ with TimescaleDB |
| Redis | 6+ | 7+ |
| Memory | 2GB | 4GB+ |
| Storage | 10GB | 50GB+ |

### Quick Installation

```bash
# Clone repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# Using Docker (Recommended)
docker-compose up -d

# Access the application
# Frontend: http://localhost:5173
# Backend API: http://localhost:8081
# MailHog (dev): http://localhost:8025
# PgAdmin: http://localhost:5050
```

### Default Credentials

| Service | Username | Password |
|---------|----------|----------|
| Application | admin@example.com | password |
| PgAdmin | admin@admin.com | admin |

---

## Architecture Overview

### Multi-Tenant Design

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer                        │
└─────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────┐
│                   Application Layer                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │   React     │  │   Laravel   │  │   Queue     │     │
│  │   Frontend  │  │   API       │  │   Workers   │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                            │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ TimescaleDB │  │   Redis     │  │   Storage   │     │
│  │ (PostgreSQL)│  │   Cache     │  │   (Reports) │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
```

### Tenant Isolation

- **Database Level**: All queries scoped to `company_id`
- **Application Level**: Middleware enforces company context
- **UI Level**: White-label branding per company
- **Data Level**: Separate encryption keys per company

---

## User Guide

### Dashboard

The dashboard provides an at-a-glance view of your reporting platform:

- **Stats Grid**: Total customers, integrations, reports generated
- **Integrations Summary**: Status of all connected integrations
- **Upcoming Reports**: Next scheduled report generations
- **Recent Activity**: Latest system events and notifications

#### Navigation

| Link | Destination |
|------|-------------|
| Integrations "View All" | `/platform/integrations` |
| Upcoming Reports "View All" | `/platform/reports` |
| Recent Activity "View All" | `/platform/notifications` |

### Reports

#### Viewing Reports

1. Navigate to **Reports** from the sidebar
2. Use search to filter by customer name
3. Filter by status (completed, pending, failed)
4. Filter by report type
5. Click **Preview** to view the PDF
6. Click **Download** to save locally

#### Emailing Reports

1. Open a report preview
2. Click **Email Report** button
3. Enter recipient email(s) - comma-separated for multiple
4. Click **Send Email**
5. Success modal confirms delivery with recipient list

#### Pagination

- Reports display **9 per page**
- Shows "Showing X to Y of Z reports"
- Navigate using Previous/Next or page numbers

### Schedules

View and manage automated report schedules:

- **9 schedules per page** with pagination
- Click **Configure** to modify schedule settings
- Shows next scheduled run time
- Toggle active/inactive status

### Integrations

#### Viewing Integrations

- **9 integrations per page**
- Search by name, type, or status
- View sync status and last sync time

#### Actions

- **Sync Now**: Trigger manual synchronization
- **View Customers**: See customers from this integration
- **Health Check**: Verify connection status
- **Rate Limits**: View API usage limits
- **Historical Import**: Import historical data

---

## Admin Guide

### Company Settings

Navigate to **Settings** > **Company Info**:

#### Branding

| Setting | Description |
|---------|-------------|
| Company Name | Displayed in reports and UI |
| Primary Color | Brand color (hex format) |
| Secondary Color | Accent color |
| Logo | Company logo for reports |
| Favicon | Browser tab icon |

#### Contact Information

| Setting | Description |
|---------|-------------|
| Contact Email | Primary contact for reports |
| Contact Phone | Support phone number |
| Address | Physical address (multi-line) |

#### Email Delivery

| Setting | Description |
|---------|-------------|
| From Email | Sender address for reports |
| From Name | Sender display name |
| Support Email | Support contact in reports |

### User Management

- Create users with granular permissions
- Assign read/write access per feature
- Manage API keys for programmatic access

---

## Integration Guide

### Supported Integrations

| Integration | Type | Features |
|-------------|------|----------|
| Autotask PSA | PSA | Tickets, time entries, projects |
| DattoRMM | RMM | Device monitoring, backups |
| Active Directory | Identity | Users, groups, memberships |
| Exchange | Email | Mailboxes, permissions |
| ITGlue | Documentation | Knowledge base sync |
| Kaseya VSA | RMM | Device management |
| BitDefender | Security | Endpoint protection |
| Veeam | Backup | Backup status |
| RocketCyber | Security | SOC platform |

### Adding an Integration

1. Go to **Integrations** > **Add Integration**
2. Select integration type
3. Enter display name
4. Provide API credentials
5. Test connection
6. Save and sync

### Source of Truth

Designate one integration as the "source of truth" for customer names:

1. Click the star icon on an integration card
2. Confirm the selection
3. Customer names from this integration become canonical

---

## Report Configuration

### Setting Up Automated Reports

1. Navigate to **Customers**
2. Click **Configure Reports** on a customer
3. Add report schedules:
   - Select report type
   - Choose frequency (daily/weekly/monthly/yearly)
   - Set time of day
   - Toggle active status

### Delivery Settings

**Required before saving:**

| Field | Description | Required |
|-------|-------------|----------|
| Email Destination | Primary recipient | Yes |
| CC Emails | Additional recipients | No |

### Validation

The system validates before saving:

- At least one report configured
- Valid email destination
- Valid email format
- Valid CC email formats

If validation fails, a modal displays all errors.

---

## API Reference

### Authentication

```bash
# Login
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

# Response includes token for subsequent requests
Authorization: Bearer {token}
```

### Reports API

```bash
# Get report history
GET /api/reports/history?page=1&per_page=9

# Download report
GET /api/reports/{id}/download

# Email report
POST /api/reports/{id}/email
{
  "emails": "recipient@example.com, another@example.com"
}

# Get scheduled reports
GET /api/reports/scheduled
```

### Customers API

```bash
# List customers
GET /api/customers?page=1&search=acme

# Get customer reports config
GET /api/customers/{id}/reports

# Update report configuration
PATCH /api/customers/{id}/reports
{
  "schedules": [
    {
      "report_type": "uptime_monthly",
      "frequency": "monthly",
      "time_of_day": "09:00",
      "active": true
    }
  ]
}

# Update delivery destination
PATCH /api/customers/{id}/report-destination
{
  "method": "email",
  "destination": "reports@customer.com",
  "cc": ["manager@customer.com"]
}
```

### Integrations API

```bash
# List integrations
GET /api/integrations

# Trigger sync
POST /api/integrations/{id}/sync

# Health check
GET /api/integrations/{id}/health

# Get customers from integration
GET /api/integrations/{id}/customers
```

---

## Troubleshooting

### Common Issues

#### Reports Not Generating

1. Check email destination is configured
2. Verify integration is synced
3. Check queue worker is running:
   ```bash
   docker exec reporting-app php artisan queue:work --status
   ```

#### Integration Sync Failing

1. Verify API credentials
2. Check rate limits
3. Review sync logs in integration modal
4. Test connection with health check

#### Email Not Sending

1. Verify SMTP settings in `.env`
2. Check MailHog (dev) at http://localhost:8025
3. Review Laravel logs:
   ```bash
   docker exec reporting-app tail -f storage/logs/laravel.log
   ```

#### Pagination Showing Wrong Count

1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R)
3. Check network tab for API response

### Log Locations

| Log | Location |
|-----|----------|
| Laravel | `backend/storage/logs/laravel.log` |
| Queue | `backend/storage/logs/queue.log` |
| Nginx | Docker logs: `docker logs reporting-nginx` |

---

## FAQ

### General

**Q: How many customers can the platform handle?**
A: Tested with 500+ customers per company. TimescaleDB handles millions of data points efficiently.

**Q: Can I customize report templates?**
A: Yes, report templates are Blade files in `backend/resources/views/Reports/`.

**Q: Is data encrypted?**
A: Yes, at rest (PostgreSQL encryption) and in transit (TLS).

### Reports

**Q: Why are reports showing 9 per page?**
A: This is the default pagination setting for optimal UI display in a 3x3 grid.

**Q: Can I send reports to multiple recipients?**
A: Yes, enter comma-separated emails in the Email Report dialog.

**Q: What happens if email delivery fails?**
A: The system retries 3 times with exponential backoff. Failed deliveries are logged.

### Integrations

**Q: How often do integrations sync?**
A: Default is every 4 hours. Can be configured per integration.

**Q: Can I import historical data?**
A: Yes, use the Historical Import feature on each integration.

**Q: What if customer names don't match across integrations?**
A: Use the Customer Mapping feature to manually link customers.

---

## Support

- **Email**: support@athenanetworks.com.au
- **Issues**: [GitLab Issues](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/issues)
- **Documentation**: This wiki and README.md

---

## Security

### Environment File Handling

Never commit real `.env`, `.env.local`, or `.env.docker` files. Only example templates should be in version control:

- `.env.example`
- `.env.docker.example`

If a real `.env` file is accidentally committed, follow these steps:

1. Remove it from git: `git rm --cached <file>`
2. Harden `.gitignore` to exclude the file
3. Rewrite git history to purge the file: `git filter-branch` or `git filter-repo`
4. Force push the cleaned history
5. Treat the leaked values as compromised and rotate all affected credentials

### Reporting Security Issues

Report vulnerabilities to the development team before making the repository public.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

---

*Last updated: August 31, 2026*
