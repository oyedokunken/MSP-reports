# SharePoint Global Integration Feature

## Overview

This feature allows the system to integrate with company-level SharePoint servers. This enables reports to be delivered directly to specific SharePoint folders. Users can browse folders, create new ones, and select a destination folder during the report configuration process. **Multiple SharePoint integrations can be configured for different sites.**

## Requirements

### 1. SharePoint Global Setup

-   **Company Level**: The integration is configured once per company (Tenant).
-   **Authentication**: Uses Microsoft Graph API credentials (Tenant ID, Client ID, Client Secret).
-   **Configuration**: Users need to input:
    -   Tenant ID
    -   Client ID
    -   Client Secret
    -   SharePoint Site URL (or Drive ID)

### 2. Report Configuration Update

-   **Delivery Method**: Add option to switch between "Email" and "SharePoint".
-   **Site Selection**: If multiple SharePoint integrations exist, users can select the target site.
-   **Folder Selection**:
    -   Users can browse the SharePoint directory structure.
    -   Users can select a specific folder as the report destination.
-   **Folder Creation**:
    -   Users can create new folders directly from the browser interface.

---

## Technical Implementation Plan

### Phase 1: Integration Setup (Backend & UI)

**Backend:**

-   **Type**: Add `sharepoint` to the supported `integration_type` enum (or list).
-   **Model**: Use the existing `integrations` table.
    -   `credentials`: Store `client_id`, `client_secret`, `tenant_id` (encrypted).
    -   `configuration`: Store `site_url`, `drive_id`, `root_folder_path`.
-   **API**:
    -   Update `IntegrationController` to handle validation for SharePoint credentials (test connection).
    -   **Update**: Allow multiple integrations of type `SharePoint`.

**Frontend:**

-   **Add Integration Modal**: Update `AddIntegrationModal.tsx` to include a form for SharePoint.
    -   Fields: Name, Tenant ID, Client ID, Client Secret, Site URL.
-   **Integration Card**: Ensure the SharePoint logo and details are displayed correctly in `IntegrationList.tsx`.

### Phase 2: SharePoint File Browser (API & UI Component)

**Backend API:**

-   `GET /api/integrations/{id}/sharepoint/browse`:
    -   Params: `path` (optional, default `/`), `drive_id` (optional).
    -   Returns: List of folders and files in the specified path using MS Graph API.
-   `POST /api/integrations/{id}/sharepoint/folders`:
    -   Body: `path`, `name`.
    -   Action: Creates a new folder at the specified path.

**Frontend Component:**

-   **FileBrowserModal**: A reusable component to browse folders.
    -   Displays a breadcrumb of the current path.
    -   Lists folders (clickable to navigate) and files.
    -   "New Folder" button to trigger the creation API.
    -   "Select This Folder" button to confirm selection.

### Phase 3: Report Configuration Updates

**Frontend:**

-   **ConfigureReports.tsx**:
    -   Add a **Delivery Method** toggle/selector: `Email` | `SharePoint`.
    -   **Email Mode**: Show existing email input.
    -   **SharePoint Mode**:
        -   Show "Select Destination Path" input (read-only text).
        -   **Select Integration**: Dropdown to choose SharePoint site if multiple are configured.
        -   "Browse" button to open `FileBrowserModal`.
-   **State**: Update `report_configuration` state to store `delivery_method`, `integration_id`, and `sharepoint_path`.

**Backend:**

-   **Database**: Update `customers` table json column `report_configuration` (or `delivery` settings) to store:
    ```json
    {
        "method": "sharepoint",
        "integration_id": 123,
        "path": "/Reports/Customer A/Monthly",
        "drive_id": "b!..."
    }
    ```
-   **DeliverReportJob**:
    -   Check `delivery_method`.
    -   If `sharepoint`:
        -   Retrieve SharePoint integration credentials.
        -   Use Graph API (`PUT /drives/{drive-id}/root:/{path}/{filename}:/content`) to upload the generated PDF.
    -   If `email`: Continue with existing logic.

### Phase 4: Permissions & Error Handling

-   **Validation**: Ensure the SharePoint token is valid before attempting upload.
-   **Error Logs**: Log failed uploads to `sync_logs` or `report_delivery_logs`.
-   **Retry Logic**: Implement retries for temporary SharePoint API failures.

---

## API Reference (Draft)

### 1. Browse SharePoint

**GET** `/api/integrations/{integration}/sharepoint/browse`
**Query Params:**

-   `path`: string (default `/`)
-   `next_link`: string (for pagination)

**Response:**

```json
{
    "data": [
        { "name": "Financials", "type": "folder", "id": "01ABC..." },
        { "name": "Q1 Report.pdf", "type": "file", "id": "01ABD..." }
    ],
    "path": "/Shared Documents/Financials"
}
```

### 2. Create Folder

**POST** `/api/integrations/{integration}/sharepoint/folders`
**Body:**

```json
{
    "path": "/Shared Documents",
    "name": "New Folder Name"
}
```

**Response:**

```json
{
    "success": true,
    "data": { "name": "New Folder Name", "id": "..." }
}
```

---

## Implementation Checklist

### Phase 1: Integration Setup

-   [x] **Backend**: Add `sharepoint` to supported integration types and update validations.
-   [x] **Frontend**: Update `AddIntegrationModal` with SharePoint form fields.
-   [x] **Frontend**: Update `IntegrationList` to display SharePoint integrations correctly.

### Phase 2: File Browser

-   [x] **Backend**: Implement `GET /browse` endpoint (Microsoft Graph API).
-   [x] **Backend**: Implement `POST /folders` endpoint (Microsoft Graph API).
-   [x] **Frontend**: Create `FileBrowserModal` component with navigation and creation UI.

### Phase 3: Configuration & Delivery

-   [x] **Frontend**: Update `ConfigureReports` with "Delivery Method" toggle.
-   [x] **Frontend**: Integrate `FileBrowserModal` into the configuration flow.
-   [x] **Backend**: Update `DeliverReportJob` to handle SharePoint uploads.
-   [x] **database**: Ensure `report_configuration` JSON structure supports the new fields.

### Phase 4: Polish

-   [x] **Validation**: Verify token validity before uploads.
-   [x] **Error Handling**: Add retry logic and error logging.
-   [x] **Testing**: Verify end-to-end flow from setup to delivery.

---

## Testing & User Flow

Here is the end-to-end user flow to test the SharePoint integration:

### 1. Connect Integration (Supports Multiple)

1.  Navigate to **Settings > Integrations** in the application.
2.  Click the **Add Integration** button.
3.  Select **SharePoint** from the list.
4.  Enter your Microsoft Graph credentials:
    -   **Client ID**
    -   **Client Secret**
    -   **Tenant ID**
    -   **Site URL** (e.g., `https://yourdomain.sharepoint.com/sites/Marketing`)
5.  Click **Verify & Connect**.
    -   _Expected Result:_ You should see a success message, and the integration should appear in the list with an "Active" status.
6.  _(Optional)_ Repeat steps 2-5 to add additional SharePoint Sites (e.g., Finance, HR).

### 2. Configure Report Delivery

1.  Navigate to **Reports > Configure Reports**.
2.  On the right sidebar ("Delivery & Actions"), locate the **Delivery Method** section.
3.  Click the **SharePoint** button (this option should now be visible).
4.  **Select Integration**: If you configured multiple SharePoint sites, select the target one from the dropdown.
5.  Click the **Folder Icon** button to open the file browser.
    -   _Test:_ Navigate through folders to ensure browsing works.
    -   _Test:_ Click the "New Folder" icon, enter a name (e.g., "Monthly Reports"), and verify it appears.
6.  Select a destination folder and click **Select**.
7.  Click **Save Configuration**.
    -   _Expected Result:_ A "Saved" toast message should appear.

### 3. Generate & Deliver

1.  In the same sidebar, click the **Generate** button.
2.  Select a Date Range (e.g., Last Month).
3.  Click **Generate**.
    -   _Expected Result:_ The progress bar will fill up as reports are generated and uploaded.
4.  Once complete, go to your actual **SharePoint Site** in your browser.
5.  Navigate to the folder you selected.
    -   _Expected Result:_ You should see the generated PDF report files there.

### 4. Troubleshooting (If needed)

-   **Logs:** Check `storage/logs/laravel.log` for entries starting with `Report delivered successfully` or `SharePoint Delivery Failed`.
-   **Failures:** If delivery fails, the UI will show a red "Failures" box. Click "Retry" to attempt the upload again.

The implementation is complete! Let me know if you run into any issues during testing.
