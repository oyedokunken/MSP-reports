# Integration Management API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) and appropriate permissions (`integrations,view`, `integrations,manage`, `integrations,sync`).

---

## 1. List Integrations

**Endpoint:** `GET /integrations`

**Description:** Retrieves a list of all configured integrations and available integration types.

**Permission Required:** `integrations,view`

**Response:**

- `available_types`: List of supported integration types (e.g., Autotask, DattoRMM).
- `configured`: List of active integrations for the company.

---

## 2. Get Integration Config Schema

**Endpoint:** `GET /integrations/config/{integrationType}`

**Description:** Retrieves the configuration schema (fields, validation) for a specific integration type. Used for building dynamic forms.

**Permission Required:** `integrations,manage`

---

## 3. Create Integration

**Endpoint:** `POST /integrations`

**Description:** Creates a new integration instance.

**Permission Required:** `integrations,manage`

**Request Body:**

- `integration_type` (required): Type of integration (e.g., 'Autotask').
- `display_name` (required): Friendly name.
- `credentials` (required): JSON object with API keys/secrets.
- `configuration` (optional): JSON object with settings.
- `is_source_of_truth` (optional): Boolean.

---

## 4. Update Integration

**Endpoint:** `PATCH /integrations/{id}`

**Description:** Updates an existing integration's credentials or configuration.

**Permission Required:** `integrations,manage`

**Request Body:**

- `display_name` (optional)
- `credentials` (optional)
- `configuration` (optional)
- `status` (optional)

---

## 5. Delete Integration

**Endpoint:** `DELETE /integrations/{id}`

**Description:** Removes an integration and all its associated data.

**Permission Required:** `integrations,manage`

---

## 6. Trigger Manual Sync

**Endpoint:** `GET /integrations/{id}/sync`

**Description:** Queues a manual synchronization job for the integration.

**Permission Required:** `integrations,sync`

---

## 7. Get Integration Customers

**Endpoint:** `GET /integrations/{id}/customers`

**Description:** Retrieves the raw list of customers imported from this integration.

**Permission Required:** `integrations,view`

---

## 8. Integration Sync Logs

**Endpoint:** `GET /integrations/{id}/sync-logs`

**Description:** Retrieves the history of synchronization jobs.

**Permission Required:** `integrations,view`

**Query Parameters:**

- `limit` (optional): Number of logs to return (default: 50).

---

## 9. Integration Health Check

**Endpoint:** `GET /integrations/{id}/health`

**Description:** Performs a live connection test to verify API credentials and connectivity.

**Permission Required:** `integrations,view`

---

## 10. Rate Limit Status

**Endpoint:** `GET /integrations/{id}/rate-limit`

**Description:** Checks the current API usage against rate limits for the integration.

**Permission Required:** `integrations,view`

---

## 11. Start Historical Import

**Endpoint:** `POST /integrations/{id}/historical-import`

**Description:** Initiates a deep historical data import (e.g., past tickets/time entries).

**Permission Required:** `integrations,sync`

**Request Body:**

- `start_date` (required): ISO date string.
- `end_date` (required): ISO date string.

---

## 12. Dismiss Historical Import Failure

**Endpoint:** `DELETE /integrations/{id}/historical-import/dismiss`

**Description:** Clears a failed or stuck historical import state, allowing the user to retry.

**Permission Required:** `integrations,sync`

---

## 13. Track Historical Import Job

**Endpoint:** `GET /integrations/{id}/historical-import-jobs`

**Description:** Retrieves status of background historical import jobs.

**Permission Required:** `integrations,sync`

---

## 14. Set Source of Truth

**Endpoint:** `PATCH /company/source-integration`

**Description:** Designates a specific integration as the primary "Source of Truth" for customer data.

**Permission Required:** `integrations,manage`

**Request Body:**

- `integration_id` (required): ID of the integration to set as SOT.

---

## 15. Browse SharePoint (SharePoint Only)

**Endpoint:** `GET /integrations/{id}/sharepoint/browse`

**Description:** Browses files and folders in a connected SharePoint site.

**Permission Required:** `integrations,view`

**Query Parameters:**

- `path` (optional): Folder path to browse.

---

## 16. Create SharePoint Folder (SharePoint Only)

**Endpoint:** `POST /integrations/{id}/sharepoint/folders`

**Description:** Creates a new folder in the connected SharePoint site.

**Permission Required:** `integrations,manage`

**Request Body:**

- `path` (required): Parent path.
- `name` (required): New folder name.
