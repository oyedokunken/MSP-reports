# Report Configuration & Analytics API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) and appropriate permissions (`reports,view`, `reports,configure`, `reports,generate`, `reports,download`).

---

# A. Report Configuration

## 1. Get Available Reports

**Endpoint:** `GET /reports`

**Description:** Retrieves a list of all report types available in the system, including their metadata (category, supported formats, required integrations).

**Permission Required:** `reports,view`

---

## 2. Get Available Reports for Customer

**Endpoint:** `GET /customers/{id}/available-reports`

**Description:** Returns reports available for a specific customer, filtered by their active integrations.

**Permission Required:** `reports,view`

**Query Parameters:**

- `frequency` (optional): Filter by frequency capability (e.g., 'monthly').

---

## 3. Get Customer Report Schedules

**Endpoint:** `GET /customers/{id}/reports`

**Description:** Retrieves the current reporting configuration and active schedules for a customer.

**Permission Required:** `reports,view`

---

## 4. Update Customer Report Schedules

**Endpoint:** `PATCH /customers/{id}/reports`

**Description:** Updates the active report schedules for a customer.

**Permission Required:** `reports,configure`

**Request Body:**

- `schedules` (required): Array of schedule objects.

---

## 5. Update Report Destination

**Endpoint:** `PATCH /customers/{id}/report-destination`

**Description:** Updates the email destination and CC list for a customer's reports.

**Permission Required:** `reports,configure`

**Request Body:**

- `destination` (required): Primary email address.
- `cc` (optional): Array of CC email addresses.

---

## 6. Update Individual Schedule

**Endpoint:** `PATCH /reports/schedules/{schedule_id}`

**Description:** Updates settings for a single existing schedule.

**Permission Required:** `reports,configure`

---

## 7. Reset Schedule

**Endpoint:** `POST /reports/schedules/{schedule_id}/reset`

**Description:** Resets a schedule to its default template settings.

**Permission Required:** `reports,configure`

---

## 8. Delete Schedule

**Endpoint:** `DELETE /reports/schedules/{id}`

**Description:** Removes a specific report schedule.

**Permission Required:** `reports,configure`

---

# B. Report Generation (Manual)

## 9. Generate Report (Manual Trigger)

**Endpoint:** `POST /reports/generate/{customer_id}`

**Description:** Manually triggers report generation for a customer. Can optionally preview without sending.

**Permission Required:** `reports,generate`

**Request Body:**

- `report_type` (optional): Specific report type to run. If omitted, runs all active schedules.
- `start_date` (optional): Custom start date.
- `end_date` (optional): Custom end date.
- `preview_only` (optional): Boolean. If true, generates PDF but does not email it.

---

## 10. Generate from Schedule

**Endpoint:** `POST /reports/schedules/{schedule_id}/generate`

**Description:** Manually triggers a specific schedule.

**Permission Required:** `reports,generate`

---

## 11. Bulk Generate Reports

**Endpoint:** `POST /reports/bulk-generate`

**Description:** Triggers report generation for multiple customers at once.

**Permission Required:** `reports,generate`

**Request Body:**

- `customer_ids` (required): Array of customer IDs.

---

# C. Reporting & Analytics

## 12. Get Scheduled Reports List

**Endpoint:** `GET /reports/scheduled`

**Description:** Retrieves a flat list of all active schedules across all customers.

**Permission Required:** `reports,view`

**Query Parameters:**

- `include_inactive` (optional): Boolean.

---

## 13. Get Report History

**Endpoint:** `GET /reports/history`

**Description:** Retrieves a log of generated reports (completed and failed).

**Permission Required:** `reports,view`

**Query Parameters:**

- `customer_id` (optional): Filter by customer.
- `per_page` (optional): Pagination limit.

---

## 14. Download Report

**Endpoint:** `GET /reports/{id}/download`

**Description:** Downloads the PDF of a generated report.

**Permission Required:** `reports,download`

---

## 15. Email Report

**Endpoint:** `POST /reports/{id}/email`

**Description:** Re-sends an existing report to specific email addresses.

**Permission Required:** `reports,generate`

**Request Body:**

- `emails` (required): Array of email addresses.

---

## 16. Deliver Report

**Endpoint:** `POST /reports/{id}/deliver`

**Description:** Retries the standard delivery (to customer's configured destination) for a report.

**Permission Required:** `reports,generate`

---

## 17. Generate Share Link

**Endpoint:** `POST /reports/{id}/share`

**Description:** Generates a temporary, public URL to view/download the report.

**Permission Required:** `reports,view`

---

## 18. Preview Email

**Endpoint:** `GET /reports/{id}/email-preview`

**Description:** Returns the HTML content of the email that would be sent for a report. Used for inspection/debugging.

**Permission Required:** `reports,view`

---

## 19. Batch Delete Reports

**Endpoint:** `DELETE /reports`

**Description:** Deletes multiple generated reports at once.

**Permission Required:** `reports,generate`

**Request Body:**

- `report_ids` (required): Array of IDs.

---

## 20. Discard Reports

**Endpoint:** `POST /reports/batch-discard`

**Description:** "Discards" reports (soft delete / status change) without permanently removing them immediately.

**Permission Required:** `reports,generate`

---

## 21. Download Shared Report (Public)

**Endpoint:** `GET /reports/shared/{token}`

**Description:** Public endpoint to download a report via a share link token. No authentication required.

**Permission Required:** None
