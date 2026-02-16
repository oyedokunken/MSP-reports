# License Management API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) and appropriate permissions (`licenses,view` or `licenses,manage`).

---

## 1. List All Licenses

**Endpoint:** `GET /licenses`

**Description:** Retrieves all active and expiring licenses for the company, along with user license status and enforcement settings.

**Permission Required:** `licenses,view`

**Response:**

- `active_licenses`: List of currently active licenses.
- `expiring_soon`: List of licenses expiring within 30 days.
- `user_license_status`: Current user count vs. licensed seats.
- `license_enforcement_enabled`: Boolean indicating if strict enforcement is on.

---

## 2. Get License Usage Stats

**Endpoint:** `GET /licenses/usage`

**Description:** Provides a summary of license consumption across integrations, users, and data retention.

**Permission Required:** `licenses,view`

**Response:**

- `integrations`: Active integrations count vs. licensed count.
- `users`: Active users count vs. licensed seats.
- `retention`: Maximum retention months allowed vs. configured.

---

## 3. Check Integration License

**Endpoint:** `POST /licenses/check/integration`

**Description:** Verifies if the company has a valid license to activate a specific type of integration.

**Permission Required:** `licenses,manage`

**Request Body:**

- `integration_type` (required): The type of integration to check (e.g., 'Autotask').

**Response:**

- Returns boolean/status indicating eligibility.

---

## 4. Check Retention License

**Endpoint:** `POST /licenses/check/retention`

**Description:** Verifies if the company has a valid license to extend data retention to a requested number of months.

**Permission Required:** `licenses,manage`

**Request Body:**

- `requested_months` (required): Number of months desired.

---

## 5. Check User License

**Endpoint:** `POST /licenses/check/user`

**Description:** Verifies if the company's license allows creating a new user (i.e., hasn't exceeded seat count).

**Permission Required:** `licenses,manage`

**Response:**

- `can_create`: Boolean.
- `license_status`: Current usage details.
