# Reporting System Documentation

## Overview
This document outlines the end-to-end flow of the reporting system, from backend generation to frontend display, and details the currently supported reports and their required integrations.

## Backend Architecture

The backend reporting system is built on a modular architecture that allows for easy addition of new report types and integrations.

### 1. Report Discovery (`ReportRegistry`)
*   **Location:** `app/Services/ReportRegistry.php`
*   **Function:** Automatically scans the `app/Reports` directory for report configurations (`config.php`).
*   **Mechanism:** It registers available reports into a central registry, making them accessible to the rest of the application.
*   **Caching:** Report definitions are cached for performance.

### 2. API Layer (`ReportConfigController`)
*   **Location:** `app/Http/Controllers/Api/ReportConfigController.php`
*   **Endpoints:**
    *   `GET /api/customers/{id}/available-reports`: Lists reports available for a specific customer based on their active integrations.
    *   `POST /api/reports/generate/{customer_id}`: Triggers on-demand report generation.
    *   `PATCH /api/customers/{id}/reports`: Updates report schedules.
*   **Filtering Logic:** The controller filters the global list of reports against the customer's linked integrations. For example, if a customer only has "Autotask", they will not see "Backup Status" reports (which require Datto/Veeam).

### 3. Generation Service (`ReportGenerationService`)
*   **Location:** `app/Services/ReportGenerationService.php`
*   **Process:**
    1.  **Load Class:** Instantiates the specific Report Class (e.g., `BackupStatus`).
    2.  **Fetch Data:** Calls the `generate()` method on the report class to fetch data from the database (e.g., `integration_data` table).
    3.  **Render HTML:** Uses Blade templates (`resources/views/reports/*`) to render the report content.
    4.  **PDF Conversion:** Sends the HTML to **Gotenberg** (a Dockerized PDF engine) to generate a high-quality PDF.
    5.  **Storage:** Saves the PDF to the `reports` disk.

### 4. Delivery (`DeliverReportJob`)
*   **Location:** `app/Jobs/DeliverReportJob.php`
*   **Function:** Handles the email delivery of the generated PDF to the customer's configured recipients.

---

## Frontend Integration

The frontend interacts with the reporting system primarily through the **Customer Details** page.

### 1. Fetching Available Reports
*   **Hook:** `useCustomerIntegrationDetails` (in `frontend/src/pages/customers/hooks.ts`)
*   **Flow:**
    1.  Frontend calls `GET /api/customers/{id}/available-reports`.
    2.  Backend returns a list of reports valid for that customer.
    3.  Frontend renders the "Reports" tab, showing only relevant options.

### 2. Generating Reports
*   **Action:** User clicks "Generate Now" or configures a schedule.
*   **Flow:**
    1.  Frontend calls `POST /api/reports/generate/{customer_id}`.
    2.  Backend processes the request asynchronously.
    3.  Frontend polls for status or waits for the email.

---

## Supported Reports & Integrations

The following reports are currently implemented and "working" (i.e., they have configuration and logic).

| Report Name | ID | Description | Required Integrations | Data Types |
| :--- | :--- | :--- | :--- | :--- |
| **Backup Status** | `backup_status` | Backup success rates, failures, and storage metrics. | **Datto**, **Veeam** | `backup_status` |
| **Monthly Comprehensive** | `monthly_comprehensive` | Customizable combined report (Executive Summary). | **Multiple** (Configurable) | `uptime_metric`, `ticket`, `backup_status`, `security_alert` |
| **Security Summary** | `security_summary` | Security alerts, incidents, and threat analysis. | **Datto**, **BitDefender**, **RocketCyber**, **ThreatLocker**, **Barracuda**, **WatchguardAuthPoint** | `security_alert`, `security_incident` |
| **Ticket Summary** | `ticket_summary` | Support ticket analysis and technician activity. | **Autotask**, **Kaseya** | `ticket`, `time_entry` |
| **Monthly Uptime** | `uptime_monthly` | Device uptime metrics and availability tracking. | **Datto**, **Kaseya** | `uptime_metric` |

### Notes:
*   **Multiple Integrations:** The "Monthly Comprehensive" report is a "meta-report" that pulls data from multiple sources. It requires the customer to have at least one valid integration for the sections they want to include.
*   **Data Availability:** A report will only generate if there is actual data in the `integration_data` table for the requested period.
