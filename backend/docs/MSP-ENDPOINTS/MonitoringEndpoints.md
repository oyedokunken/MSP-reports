# Monitoring & Health Check API Endpoints

## Base URL

`/api`

## Authentication

- **Public Endpoints**: `/health` and sub-routes do NOT require authentication.
- **Authenticated Endpoints**: `/monitoring/*` and `/audit-logs/*` require a valid API token (`monitoring,view` or `security,view_logs` permissions).

---

## 1. System Health Check (Public)

**Endpoint:** `GET /health`

**Description:** Performs a comprehensive check of all system components (Database, Redis, Storage, Email, Scheduler, etc.). Returns a summary status.

**Response:**

- `status`: 'healthy' or 'unhealthy'
- `checks`: Detailed status for each component.

### Specific Component Checks

You can also check individual components directly:

- `GET /health/database`: Check DB connection.
- `GET /health/redis`: Check Redis connection.
- `GET /health/storage`: Check disk space availability.
- `GET /health/email`: Check SMTP connectivity.
- `GET /health/scheduler`: Check if recent jobs have run.
- `GET /health/reports`: Check if PDF generation service is reachable.
- `GET /health/external`: Check external internet connectivity.

---

## 2. Detailed System Status (Authenticated)

**Endpoint:** `GET /monitoring/status`

**Description:** Returns a detailed status dashboard including counts of users, integrations, templates, and queue metrics.

**Permission Required:** `monitoring,view`

**Response:**

- `system`: Uptime and component health.
- `integrations`: Total/Active/Error counts.
- `reports`: Recent generation statistics.
- `users`: User counts.
- `queue`: Current pending jobs.
- `redis`: Memory usage metrics.

---

## 3. Integration Metrics (Authenticated)

**Endpoint:** `GET /monitoring/integrations`

**Description:** Retrieves synchronization performance metrics for all integrations over a specified period.

**Permission Required:** `monitoring,view`

**Query Parameters:**

- `days` (optional): Number of days to look back (default: 7).

**Response:**

- List of integrations with success/failure counts, sync rates, and rate limit hits.

---

## 4. Report Generation Metrics (Authenticated)

**Endpoint:** `GET /monitoring/reports`

**Description:** Retrieves statistics on report generation success, failure, and delivery rates.

**Permission Required:** `monitoring,view`

**Query Parameters:**

- `days` (optional): Number of days to look back (default: 7).

**Response:**

- `metrics`: Total reports, success rate, avg generation time.
- `recent_failures`: Details of the last 5 failed reports.

---

## 5. Monthly Report Analytics (Authenticated)

**Endpoint:** `GET /monitoring/reports/monthly`

**Description:** Aggregates report generation counts by month for historical trending.

**Permission Required:** `monitoring,view`

**Query Parameters:**

- `months` (optional): Number of months to retrieve (default: 6).

---

## 6. Activity Trends (Authenticated)

**Endpoint:** `GET /monitoring/activity-trends`

**Description:** Returns a combined timeline of system activity (syncs, reports, jobs, logins) for charting.

**Permission Required:** `monitoring,view`

**Query Parameters:**

- `days` (optional): Number of days to look back (default: 7).

---

## 7. Queue Status (Authenticated)

**Endpoint:** `GET /monitoring/queue`

**Description:** Provides detailed insights into the job queue (Redis or Database driver), including pending jobs per queue and failed job counts.

**Permission Required:** `monitoring,view`

---

## 8. Audit Logs (Authenticated)

**Endpoint:** `GET /audit-logs`

**Description:** Retrieves security and activity logs for the company.

**Permission Required:** `security,view_logs`

**Query Parameters:**

- `page` (optional): Page number.
- `per_page` (optional): Logs per page.
- `search` (optional): Filter by keyword.
- `event_type` (optional): Filter by action (e.g., 'user.login').
- `date_from` (optional): Start date.
- `date_to` (optional): End date.

---

## 9. Audit Log Stats (Authenticated)

**Endpoint:** `GET /audit-logs/stats`

**Description:** Returns aggregated statistics about audit events (e.g., top users, common actions).

**Permission Required:** `security,view_logs`

---

## 10. Export Audit Logs (Authenticated)

**Endpoint:** `GET /audit-logs/export`

**Description:** Downloads a CSV export of filtered audit logs.

**Permission Required:** `security,view_logs`
