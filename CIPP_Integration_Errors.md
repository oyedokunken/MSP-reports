# CIPP Integration: Error & Resolution Log

This document tracks all API errors encountered during the implementation of the CIPP Integration. These errors are generally environmental (permissions, configuration, or instance-side) rather than code-related.

## Status 403: Forbidden (Unauthorized)
**Symptom**: The API returns a 403 Forbidden response.
**Root Cause**: The CIPP Service Principal (API Client) has the `readonly` role, but the endpoint requested is classified by CIPP as "Sensitive" or requires specific management permissions.

| Endpoint | Affected Resource | Fix / Requirement |
| :--- | :--- | :--- |
| `/api/ListDefenderState` | Defender Device Status | Change API User role to `Admin` or `Security Reader` in CIPP UI. |
| `/api/ListDefenderTVM` | Vulnerability Data | Change API User role to `Admin` or `Security Reader` in CIPP UI. |
| `/api/ExecAlertsList` | Security Alerts | Ensure "Advanced Security" permissions are granted to the API role. |
| `/api/ListNamedLocations` | Conditional Access | Requires `Admin` base role in CIPP. |
| `/api/ExecGDAPRoleTemplate`| GDAP Templates | Requires `Admin` base role in CIPP. |
| `/api/ListDevices` | Intune Managed Devices | Requires `Endpoint Manager` permissions in CIPP. |
| `/api/ListAPDevices` | Autopilot Inventory | Requires `Endpoint Manager` permissions in CIPP. |
| `/api/ExecJITAdmin` | JIT Elevations | Requires elevated management role in CIPP. |

---

## Status 400: Bad Request
**Symptom**: The API returns a 400 error, often with a message about missing context.
**Root Cause**: Missing integration links between CIPP and external services (like Microsoft Partner Center).

| Endpoint | Affected Resource | Fix / Requirement |
| :--- | :--- | :--- |
| `/api/listCSPLicenses` | CSP License Costs | Link a **Microsoft Partner Center** account in CIPP Settings > Integrations. |

---

## Status 404: Not Found
**Symptom**: The endpoint URL does not exist on the current CIPP instance.
**Root Cause**: Version mismatch or uninstalled Azure Function modules.

| Endpoint | Affected Resource | Fix / Requirement |
| :--- | :--- | :--- |
| `/api/ListMailboxUsage` | Mailbox Stats | Update CIPP to the latest version. Ensure "Exchange" modules are enabled. |
| `/api/ListExchangeProtection`| Anti-Spam/Phish | Update CIPP to the latest version. |

---

## Status 500: Server Error / Unauthorized Exception
**Symptom**: The server crashes or returns an internal error code (e.g., `S2SUnauthorized`).
**Root Cause**: Missing M365 API permissions (Graph Consent) or server-side null-pointer exceptions in CIPP.

| Endpoint | Affected Resource | Root Cause | Fix / Requirement |
| :--- | :--- | :--- | :--- |
| `/api/ListTeamsActivity` | Teams Adoption Stats | `S2SUnauthorized: Invalid permission.` | Grant **`Reports.Read.All`** in the M365 Tenant / CIPP Consent screen. |
| `/api/ListCSPsku` | CSP SKU Inventory | Server crash | Likely requires Partner Center integration to be active to avoid code crash. |

---

## Recommendation for MSP Reporting
To resolve 90% of these errors and enable full reporting:
1.  **CIPP Role**: Change the API Service Principal role from `readonly` to `admin`.
2.  **M365 Consent**: Re-run the **"CIPP Permission Check"** in the CIPP UI and ensure all "Read" permissions (especially `Reports.Read.All` and `SecurityEvents.Read.All`) are green.
3.  **Integrations**: Connect the **Partner Center** if CSP license reporting is required.
