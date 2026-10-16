# MSP Reports: Complete System Walkthrough

> Dashboard → Every Feature: how every piece of the system connects, page by page.

**Version:** 1.0.0
**Last Updated:** 13 February 2026

---

## Table of Contents

1. [Dashboard](#-dashboard)
2. [Integrations](#-integrations)
3. [Customers](#-customers)
4. [Reports](#-reports)
5. [Schedules](#-schedules)
6. [Templates](#-templates)
7. [Admin Pages](#-admin-pages)
8. [Settings & Profile](#-settings--profile)
9. [Real-Time Features](#-real-time-features)
10. [Authentication](#-authentication)

---

## Dashboard

**Route:** `/platform/dashboard`
**API:** `GET /api/dashboard/summary` → `DashboardController@getSummary`
**Caching:** 30-second server-side cache per company, 30-second React Query stale time

### Widgets

| Widget | Data Source | What It Shows | Clicks To |
|--------|------------|---------------|-----------|
| Imported Customers | `IntegrationCustomer` count across all integrations | Total raw customers pulled from all integrations | - |
| Mapped Customers | `Customer` table count | Customers mapped to master list (shows "X of Y mapped") | - |
| Unmapped Customers | `IntegrationCustomer` where `customer_id IS NULL` | Customers awaiting mapping | - |
| System Users | `User` table count + active filter | Total users and active count | - |
| Schedules Summary | `ReportSchedule` count + active filter | Total/active report schedules | `/platform/schedules` |
| Integrations Summary | All `Integration` records with status, last sync, customer counts | List of integrations with sync status badges | `/platform/integrations` |
| Reports Generated Chart | `charts.monthlyReports`: aggregated from `GeneratedReport` | Bar chart of reports generated per month | - |
| Health & Rate Limit Chart | `charts.activityTrends`: aggregated from `AuditLog` + `SyncLog` | Line chart: syncs, failed syncs, rate limits, reports, logins | - |

### Data Flow

```
Browser → GET /api/dashboard/summary (with auth cookie)
       → DashboardController checks 30s cache
       → If miss: queries Company stats, Integrations, Schedules, Charts
       → Returns unified JSON response
       → React Query caches for 30s, renders all widgets
```

---

## Integrations

**Route:** `/platform/integrations`
**API:** `GET /api/integrations` → `IntegrationController@getIntegrations`

### Actions

| Button/Action | API Call | What Happens |
|--------------|----------|-------------|
| Add Integration | `POST /api/integrations` | Creates integration record, shows AI walkthrough modal |
| Manual Sync | `GET /api/integrations/{id}/sync` | Dispatches `SyncIntegrationJob` → calls integration's `sync()` method |
| Test Integration | `GET /api/integrations/{id}/health` | Calls integration's `healthCheck()` → tests API connectivity |
| Edit Settings | `PATCH /api/integrations/{id}` | Updates credentials/config |
| View Sync Logs | `GET /api/integrations/{id}/sync-logs` | Shows `SyncLog` records for this integration |
| Health Check | `GET /api/integrations/{id}/health` | Tests connection, returns status |
| Rate Limit Status | `GET /api/integrations/{id}/rate-limit` | Shows Redis-tracked rate limit counters |
| Import Historical Data | `POST /api/integrations/{id}/historical-import` | Creates `HistoricalImportJob`, dispatches chunked import jobs |
| View Import Jobs | `GET /api/integrations/{id}/historical-import-jobs` | Lists `HistoricalImportJob` records with progress |
| Set as Master List | `PATCH /api/company/source-integration` | Sets `is_source_of_truth` on integration, creates master customers |
| Delete Integration | `DELETE /api/integrations/{id}` | Soft-deletes integration and all related data |
| View Customers (eye icon) | Navigates to `/platform/integrations/{name}/view` | Shows `IntegrationPreview` with live data from integration |

### Integration Preview

**Route:** `/platform/integrations/{name}/view`

| API Call | Purpose |
|----------|---------|
| `GET /api/integrations/{id}/preview/template` | Gets display template (tables, key-value, lists) |
| `GET /api/integrations/{id}/preview/data` | Gets live data from integration API |
| `GET /api/integrations/{id}/customers` | Lists integration customers |

### Sync Flow

```
User clicks "Sync" → GET /api/integrations/{id}/sync
  → SyncIntegrationJob dispatched to queue
  → Job calls integration's sync() method
  → Integration fetches data from external API (Datto, CIPP, Autotask, etc.)
  → Data mapped via Mapper class → stored in IntegrationCustomer + IntegrationData
  → BatchAutoMatchCustomersJob dispatched for unmapped customers
  → SyncLog created with results
  → AuditLog created → AIWatcherService sends contextual message
  → Frontend polls for updated status
```

### Supported Integrations (22)

| Category | Integrations |
|----------|-------------|
| PSA & Documentation | Autotask, ITGlue |
| RMM & Monitoring | Datto RMM, Kaseya VSA*, LibreNMS |
| Identity & Directory | Active Directory (push), Exchange (push), Microsoft 365*, AdminDroid |
| Security | BitDefender, RocketCyber, ThreatLocker, WatchGuard AuthPoint, Sendmarc, Vonahi |
| Backup & Infrastructure | Veeam*, Veeam ONE, Barracuda, Kemp |
| SaaS & Cloud | CIPP, SaaS Alerts, SAT* |
| Delivery | SharePoint |

*\* = Stub only (not yet implemented, returns empty results safely)*

---

## Customers

### Master List

**Route:** `/platform/customers/master`
**API:** `GET /api/customers` → Returns `Customer` records (mapped customers)

| Action | API Call | What Happens |
|--------|----------|-------------|
| Edit customer | `PATCH /api/customers/{id}` | Updates customer name/metadata |
| Merge customers | `POST /api/customers/{id}/merge` | Combines two customer records into one |
| Split customer | `POST /api/customers/{id}/split` | Splits one customer into two |
| View integrations | `GET /api/customers/{id}/integrations` | Shows which integrations this customer is mapped from |
| Configure reports | Navigate to `/platform/customers/{id}/configure-reports` | Set up report types for this customer |
| View reports | Navigate to `/platform/customers/{id}/reports` | See generated reports for this customer |

### Secondary List

**Route:** `/platform/customers/secondary`
Same API, filtered for non-source-of-truth customers.

### Map Customers

**Route:** `/platform/customers/map`
**API:** `GET /api/customers/unmapped` → Lists `IntegrationCustomer` records without `customer_id`

| Action | API Call | What Happens |
|--------|----------|-------------|
| Auto-match | `POST /api/customers/batch-auto-match` | Dispatches `BatchAutoMatchCustomersJob` (fuzzy + Levenshtein) |
| Smart match | `POST /api/customers/smart-match` | Uses embedding service for AI semantic similarity |
| Manual map | `POST /api/customers/map` | Creates `CustomerMapping` record linking integration customer to master |
| Create static | `POST /api/customers/static` | Creates customer not linked to any integration |
| Embedding status | `GET /api/customers/embedding-status` | Shows if embedding service is available |

### Customer Mapping Flow

```
Integration syncs → new IntegrationCustomer records created
  → BatchAutoMatchCustomersJob runs
  → Compares names using Levenshtein distance + soundex
  → If confidence ≥ threshold (default 85%): auto-maps
  → If below threshold: appears in "Unmapped Customers" queue
  → User can manually map, smart-match (AI), or create new customer
```

---

## Reports

**Route:** `/platform/reports`
**API:** `GET /api/reports/history` → `ReportAnalyticsController@getReportHistory`

### Actions

| Button/Action | API Call | What Happens |
|--------------|----------|-------------|
| Download | `GET /api/reports/{id}/download` | Downloads generated PDF |
| Email | `POST /api/reports/{id}/email` | Sends report via SMTP to customer |
| Email Preview | `GET /api/reports/{id}/email-preview` | Shows HTML email preview |
| Share Link | `POST /api/reports/{id}/share` | Generates HMAC-signed 1-hour share link |
| Deliver | `POST /api/reports/{id}/deliver` | Delivers via configured method (email/SharePoint) |
| Delete | `DELETE /api/reports/{id}` | Deletes generated report |
| Batch Delete | `DELETE /api/reports` | Bulk delete reports |
| Discard | `POST /api/reports/batch-discard` | Marks reports as discarded |

### Configure Reports (per customer)

**Route:** `/platform/customers/{id}/configure-reports`

| API Call | Purpose |
|----------|---------|
| `GET /api/customers/{id}/available-reports` | Lists report types available for this customer |
| `PATCH /api/customers/{id}/reports` | Updates which reports are enabled |
| `POST /api/reports/generate/{customer_id}` | Generates report immediately via Gotenberg |
| `POST /api/reports/bulk-generate` | Generates reports for multiple customers |

### Report Generation Flow

```
Schedule triggers (or manual generate)
  → ReportConfigController dispatches GenerateReportJob
  → Job loads Customer + mapped IntegrationCustomers
  → Queries IntegrationData for report period
  → Renders Blade template with data + white-label branding
  → Sends HTML to Gotenberg → receives PDF
  → Stores PDF in storage, creates GeneratedReport record
  → If delivery configured: dispatches DeliverReportJob
  → Email via SMTP or upload to SharePoint via Graph API
  → AuditLog created → AIWatcherService notifies user
```

---

## Schedules

**Route:** `/platform/schedules`
**API:** `GET /api/reports/scheduled` → `ReportAnalyticsController@getScheduledReports`

### Actions

| Button/Action | API Call | What Happens |
|--------------|----------|-------------|
| Edit schedule | `PATCH /api/reports/schedules/{id}` | Updates frequency, time, active status |
| Generate now | `POST /api/reports/schedules/{id}/generate` | Triggers immediate generation |
| Reset to template | `POST /api/reports/schedules/{id}/reset` | Resets schedule to template defaults |
| Delete | `DELETE /api/reports/schedules/{id}` | Removes schedule |

### Schedule Frequencies

- **Daily**: Every day or specific days of week
- **Weekly**: Specific day of week
- **Monthly**: Specific day of month (with fallback for short months)
- **Yearly**: Specific month and day
- **On-demand**: Manual trigger only

### Schedule Evaluation

```
Laravel Scheduler runs every minute
  → EvaluateSchedulesCommand checks all active ReportSchedules
  → Compares next_scheduled_timestamp to current time (in company timezone)
  → For due schedules: dispatches GenerateReportJob
  → Updates next_scheduled_timestamp
```

---

## Templates

**Route:** `/platform/templates`
**API:** `GET /api/report-templates` → `ReportTemplateController@index`

### Actions

| Button/Action | API Call | What Happens |
|--------------|----------|-------------|
| Create template | `POST /api/report-templates` | Creates new report template |
| Edit template | `PUT /api/report-templates/{id}` | Updates template config |
| Preview | `POST /api/report-templates/{id}/preview` | Generates preview PDF via Gotenberg |
| Apply to customer | `POST /api/report-templates/{id}/apply-to-customer` | Creates schedule for customer |
| Apply to multiple | `POST /api/report-templates/{id}/apply-to-customers` | Bulk apply to selected customers |
| View applied customers | `GET /api/report-templates/{id}/applied-customers` | Lists customers using this template |
| Delete | `DELETE /api/report-templates/{id}` | Removes template |

### Available Report Types

Retrieved via `GET /api/report-templates/all-report-types`: each report type is a PHP class in `App\Reports\` namespace implementing `ReportInterface`.

---

## Admin Pages

### Users (`/admin/users`)

| API Call | Purpose |
|----------|---------|
| `GET /api/users` | List all users in company |
| `POST /api/users` | Create new user |
| `PATCH /api/users/{id}` | Update user details |
| `PATCH /api/users/{id}/permissions` | Update granular permissions |
| `POST /api/users/{id}/reset-password` | Force password reset |
| `PATCH /api/users/{id}/deactivate` | Deactivate user |
| `PATCH /api/users/{id}/reactivate` | Reactivate user |
| `DELETE /api/users/{id}` | Delete user |
| `POST /api/users/{id}/profile-picture` | Upload profile picture |
| `GET /api/users/roles` | List available roles |

### API Keys (`/admin/api-keys`)

| API Call | Purpose |
|----------|---------|
| `GET /api/api-keys` | List all API keys |
| `POST /api/api-keys` | Create key (shown once, stored as bcrypt hash) |
| `POST /api/api-keys/{id}/revoke` | Revoke key immediately |
| `DELETE /api/api-keys/{id}` | Delete key |

### Licenses (`/admin/licenses`)

| API Call | Purpose |
|----------|---------|
| `GET /api/licenses` | List all licenses |
| `GET /api/licenses/usage` | License utilization stats |
| `POST /api/licenses/check/integration` | Check integration license |
| `POST /api/licenses/check/retention` | Check retention license |
| `POST /api/licenses/check/user` | Check user license |

### Monitoring (`/admin/monitoring`)

| API Call | Purpose |
|----------|---------|
| `GET /api/monitoring/status` | Overall system health |
| `GET /api/monitoring/queue` | Queue depth, failed jobs |
| `GET /api/monitoring/integrations` | Integration health metrics |
| `GET /api/monitoring/reports` | Report generation metrics |
| `GET /api/monitoring/reports/monthly` | Monthly report analytics |
| `GET /api/monitoring/activity-trends` | Activity trend chart data |

### System Status (`/admin/status`)

| API Call | Purpose |
|----------|---------|
| `GET /api/health` | Overall health check |
| `GET /api/health/database` | PostgreSQL/TimescaleDB connectivity |
| `GET /api/health/redis` | Redis connectivity |
| `GET /api/health/storage` | Disk space available |
| `GET /api/health/scheduler` | Laravel scheduler status |
| `GET /api/health/reports` | Report generation health |
| `GET /api/health/email` | SMTP connectivity |
| `GET /api/health/external` | External service connectivity |

### Audit Logs (`/admin/audit-logs`)

| API Call | Purpose |
|----------|---------|
| `GET /api/audit-logs` | Paginated logs with search, event type, actor, target, date filters |
| `GET /api/audit-logs/stats` | Aggregate statistics (events by type, top actors) |
| `GET /api/audit-logs/export` | CSV download (authenticated blob download) |

---

## Settings & Profile

### Company Settings (`/profile/settings`)

| API Call | Purpose |
|----------|---------|
| `GET /api/company/settings` | Get company configuration |
| `PATCH /api/company/settings` | Update company details (name, email, phone, address) |
| `PATCH /api/company/branding` | Update branding (primary color, logo) |
| `POST /api/company/logo/upload` | Upload company logo |
| `DELETE /api/company/logo/delete` | Remove company logo |
| `GET /api/company/report-naming` | Get report naming convention |
| `PATCH /api/company/report-naming` | Update report naming convention |

### Profile (`/profile/me`)

| API Call | Purpose |
|----------|---------|
| `GET /api/auth/me` | Get current user details |
| `POST /api/auth/password/change` | Change own password |
| `POST /api/users/{id}/profile-picture` | Upload profile picture |
| `DELETE /api/users/{id}/profile-picture` | Remove profile picture |

### Security (`/profile/security`)

| API Call | Purpose |
|----------|---------|
| `POST /api/auth/verify-password` | Verify current password before sensitive actions |

### Delivery Config (per customer)

| API Call | Purpose |
|----------|---------|
| `GET /api/customers/{id}/delivery` | Get delivery configuration |
| `PATCH /api/customers/{id}/delivery` | Update delivery method (email/SharePoint) |
| `POST /api/customers/{id}/delivery/test` | Test delivery connection |
| `GET /api/delivery/methods` | List available delivery methods |

---

## Real-Time Features

### Notifications

| API Call | Purpose |
|----------|---------|
| `GET /api/notifications` | List all notifications |
| `PATCH /api/notifications/{id}/read` | Mark single notification as read |
| `POST /api/notifications/mark-all-read` | Mark all as read |
| `DELETE /api/notifications/read` | Delete all read notifications |
| `DELETE /api/notifications/{id}` | Delete single notification |

**WebSocket:** Laravel Reverb broadcasts to `private-App.Models.User.{id}` channel.

### AI Watcher Chatbot

The AI Watcher monitors audit log events and pushes contextual messages with documentation links.

**Flow:**

```
1. User performs action (e.g., adds integration, syncs, generates report)
2. LogsAudit trait creates AuditLog record
3. LogsAudit calls AIWatcherService.processAuditEvent()
4. AIWatcherService maps event type to contextual message + doc link
5. Sends AIWatcherMessage notification (database + broadcast channels)
6. Frontend Echo listener receives broadcast
7. useNotificationStore intercepts 'ai_watcher' type → routes to useAIWatcherStore
8. SupportChatbot shows typing animation → injects "Activity Insight" message
9. If chatbot is closed, amber badge shows unread count
```

**Supported Event Types (25+):**
- Integration: added, synced, failed, deleted, source of truth changed
- Customer: mapped, merged, split, auto-matched
- Report: generated, delivered, failed, shared
- User: login, logout, password changed, permissions updated
- System: settings changed, API key created/revoked

---

## Authentication

### Login Flow

| API Call | Purpose |
|----------|---------|
| `GET /api/auth/config` | Get available auth methods (local, LDAP, SAML, OAuth) |
| `POST /api/auth/login` | Local username/password login |
| `GET /api/auth/microsoft/redirect` | Initiate Microsoft 365 OAuth flow |
| `GET /api/auth/microsoft/callback` | Handle OAuth callback |
| `GET /api/auth/saml/login` | Initiate SAML SSO login |
| `POST /api/auth/saml/acs` | Handle SAML assertion |
| `GET /api/auth/keycloak/redirect` | Initiate Keycloak OAuth flow |
| `GET /api/auth/keycloak/callback` | Handle Keycloak callback |
| `POST /api/auth/logout` | Logout (invalidates session + token) |

### Password Management

| API Call | Purpose |
|----------|---------|
| `POST /api/auth/password/request` | Request password reset email |
| `POST /api/auth/password/reset` | Reset password with token |
| `POST /api/auth/password/change` | Change password (authenticated) |

### Security Features

- **Session timeout:** Configurable per company (default 60 min, max 8 hours)
- **Rate limiting:** 10 req/min on auth endpoints (brute force protection)
- **Password policy:** Min 8 chars, uppercase, lowercase, number, special char, blocklist
- **CSRF:** Sanctum CSRF token validation
- **Secure cookies:** `http_only=true`, `same_site=lax`, configurable `secure`

---

## Route Summary

| Area | Routes | Auth Required |
|------|--------|--------------|
| Authentication | 14 | Mixed (public + protected) |
| Dashboard | 1 | Yes |
| Integrations | 18 | Yes + `integrations` permission |
| Customers | 14 | Yes + `customers` permission |
| Reports | 16 | Yes + `reports` permission |
| Templates | 9 | Yes + `reports.configure` permission |
| Schedules | 4 | Yes + `reports` permission |
| Users | 11 | Yes + `users` permission |
| API Keys | 4 | Yes + `api_keys` permission |
| Licenses | 5 | Yes + `company_settings` permission |
| Monitoring | 6 | Yes + `monitoring` permission |
| Health Checks | 8 | Yes + `monitoring` permission |
| Audit Logs | 3 | Yes + `security.view_logs` permission |
| Notifications | 5 | Yes |
| Company Settings | 7 | Yes + `company_settings` permission |
| Delivery | 4 | Yes + `reports` permission |
| **Total** | **154** | |
