# General & Settings API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) unless specified otherwise.

---

# A. Company Management

## 1. Get Company Settings

**Endpoint:** `GET /company/settings`

**Description:** Retrieves the company's profile, white-label settings, and subscription status.

**Permission Required:** None (Scoped to authenticated user's company)

---

## 2. Update Company Settings

**Endpoint:** `PATCH /company/settings`

**Description:** Updates core company details (name, address, contact info).

**Permission Required:** `company,manage` (Implicit via role)

---

## 3. Update Branding

**Endpoint:** `PATCH /company/branding`

**Description:** Updates white-label branding settings (colors, logo URLs, email templates).

**Permission Required:** `company,manage`

---

## 4. Upload Logo

**Endpoint:** `POST /company/logo/upload`

**Description:** Uploads a company logo or favicon.

**Permission Required:** `company,manage`

**Request Body:**

- `type` (required): 'company' or 'profile'
- `logo` (required): File

---

## 5. Delete Logo

**Endpoint:** `DELETE /company/logo/delete`

**Description:** Deletes the uploaded logo.

**Permission Required:** `company,manage`

---

## 6. Report Naming Settings

**Endpoint:** `GET /company/report-naming`
**Endpoint:** `PATCH /company/report-naming`

**Description:** Get or set the pattern used for naming generated report files (e.g., `{customer_name}_{date}`).

**Permission Required:** `company,manage`

---

# B. API Key Management

## 7. List API Keys

**Endpoint:** `GET /api-keys`

**Description:** Lists active API keys for the company.

**Permission Required:** `api_keys,view`

---

## 8. Create API Key

**Endpoint:** `POST /api-keys`

**Description:** Generates a new long-lived API key.

**Permission Required:** `api_keys,create`

---

## 9. Revoke/Delete API Key

**Endpoint:** `POST /api-keys/{id}/revoke`
**Endpoint:** `DELETE /api-keys/{id}`

**Description:** Revokes or permanently deletes an API key.

**Permission Required:** `api_keys,revoke`

---

# C. Notifications

## 10. List Notifications

**Endpoint:** `GET /notifications`

**Description:** Get recent in-app notifications for the user.

**Permission Required:** `notifications,view`

---

## 11. Mark as Read

**Endpoint:** `PATCH /notifications/{id}/read`
**Endpoint:** `POST /notifications/mark-all-read`

**Description:** Mark specific or all notifications as read.

**Permission Required:** `notifications,view`

---

# D. Dashboard

## 12. Dashboard Summary

**Endpoint:** `GET /dashboard/summary`

**Description:** Aggregated endpoint providing high-level stats for the dashboard (customers, integrations, recent activity, charts). Designed for performance with caching.

**Permission Required:** None (Basic Auth)

---

# E. Report Templates

## 13. List Templates

**Endpoint:** `GET /report-templates`

**Description:** List all available report templates (both global and customer-specific).

**Permission Required:** `templates,view`

---

## 14. Create Template

**Endpoint:** `POST /report-templates`

**Description:** Create a new report template.

**Permission Required:** `templates,edit`

---

## 15. Apply Template

**Endpoint:** `POST /report-templates/{template}/apply-to-customer`
**Endpoint:** `POST /report-templates/{template}/apply-to-customers`

**Description:** Apply a template to one or multiple customers, creating the necessary schedules.

**Permission Required:** `templates,edit`

---

# F. Delivery Configuration

## 16. Test Connection

**Endpoint:** `POST /customers/{id}/delivery/test`

**Description:** Test a specific delivery method configuration (e.g., FTP connection check).

**Permission Required:** `reports,configure`

---

## 17. Get Available Methods

**Endpoint:** `GET /delivery/methods`

**Description:** List supported delivery methods (Email, FTP, Webhook, etc.) and their schema.

**Permission Required:** `reports,view`
