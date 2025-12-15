# BitDefender GravityZone Integration Guide

## Overview
The BitDefender integration connects to the GravityZone Cloud API to sync customer companies, endpoints (devices), and active threat detections. It provides a "Security Snapshot" of the environment every 6 hours.

## Architecture

### 1. Connection
-   **API**: GravityZone Cloud API (JSON-RPC 2.0)
-   **Auth**: Basic Auth (API Key)
-   **Rate Limits**: 60 req/min, 1000 req/hour (handled by `RateLimiter` service)

### 2. Sync Schedule
-   **Frequency**: Every 6 Hours (`0 */6 * * *`)
-   **Why 6 Hours?**: BitDefender data is state-based (current infection status), not event-stream based. A 6-hour snapshot is sufficient for reporting compliance.

## Sync Logic & Performance

The sync process is optimized for **Perceived Performance** in the UI.

### Step 1: Rapid Customer Mapping (Immediate)
1.  **Fetch Companies**: Calls `getCompaniesList` to retrieve all customers (e.g., 68 companies).
2.  **Instant Match**: Immediately dispatches `BatchAutoMatchCustomersJob`.
    -   *Impact*: All 68 customers appear in the "Mapped Customers" UI list within seconds of starting the sync.

### Step 2: Deep Endpoint Scan (Background)
After customers are mapped, the system loops through every company to fetch devices.
1.  **Loop**: For each Company ID...
2.  **Fetch Endpoints**: Calls `getEndpointsList(parentId=CompanyID)`.
3.  **Store Data**: Saves devices to `integration_data` using `create()` (TimescaleDB best practice).
4.  **Fetch Threats**: Calls `getThreatDetections()` and matches them to the company.

> **Note**: This background loop can take 3-5 minutes for large accounts. The "Syncing" spinner correctly reflects this ongoing work.

## Troubleshooting: "No Data" / Empty Endpoints

**Problem**: "I have 68 customers, but they all show 0 endpoints."

**Cause**: Unassigned Devices in GravityZone.
-   The integration **strictly** matches devices to companies.
-   It iterates through Company Folders to find devices.
-   If devices are sitting in the **Root** (Unassigned) of the GravityZone console, they belong to *no company* and are skipped to prevent billing errors.

**Solution**:
1.  Log into **GravityZone Console** (`cloud.gravityzone.bitdefender.com`).
2.  Go to **Network**.
3.  Find the unassigned devices (often at the top level).
4.  **Move** them into the appropriate Company/Customer folder.
5.  Run **Sync** in the Reporting App.

## Data Storage Strategy
-   **Tables**: `integration_customers`, `integration_data`
-   **Pattern**: Insert-Only (`IntegrationData::create`)
-   **Retention**: Historical snapshots are kept for 30 days (configurable).

## Key Files
-   `BitDefenderIntegration.php`: Main logic.
-   `BitDefenderClient.php`: API communication.
-   `BitDefenderMapper.php`: Data standardization.
