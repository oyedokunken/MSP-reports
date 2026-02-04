# User Permissions and Role Matrix

This document outlines the available pages in the application, the required permissions to access them, and the access level for each default role.

## Roles Overview

The system comes with the following default roles:

- **Admin**: Full access to all system features and settings.
- **Manager**: Focus on reporting configuration, analytics, and customer management. Can view but not manage integrations/sync.
- **Technician**: Focus on technical setup, integration management, and sync operations. Limited customer editing.
- **Viewer**: Read-only access to reports and customer lists.
- **Customer**: Restricted read-only access to their own specific reports.

## Permissions Matrix

The following matrix maps Frontend Pages to Required Permissions and shows availability per Role.

| Page                    | Route                                | Required Permission     | Admin | Manager | Technician | Viewer | Customer |
| :---------------------- | :----------------------------------- | :---------------------- | :---: | :-----: | :--------: | :----: | :------: |
| **Dashboard**           |                                      |                         |       |         |            |        |          |
| Dashboard               | `/platform/dashboard`                | _(Authenticated)_       |  ✅   |   ✅    |     ✅     |   ✅   |    ✅    |
| **Integrations**        |                                      |                         |       |         |            |        |          |
| Integration List        | `/platform/integrations`             | `integrations.view`     |  ✅   |   ✅    |     ✅     |   ❌   |    ❌    |
| Integration Preview     | `/platform/integrations/:id/preview` | `integrations.view`     |  ✅   |   ✅    |     ✅     |   ❌   |    ❌    |
| **Customers**           |                                      |                         |       |         |            |        |          |
| Master Customer List    | `/platform/customers/master`         | `customers.view`        |  ✅   |   ✅    |     ✅     |   ✅   |    ❌    |
| Secondary Customer List | `/platform/customers/secondary`      | `customers.view`        |  ✅   |   ✅    |     ✅     |   ✅   |    ❌    |
| Map Customers           | `/platform/customers/map`            | `customers.map`         |  ✅   |   ❌    |     ✅     |   ❌   |    ❌    |
| Customer Details        | `/platform/customers/:id/*`          | `customers.view`        |  ✅   |   ✅    |     ✅     |   ✅   |    ❌    |
| **Reports**             |                                      |                         |       |         |            |        |          |
| Reports List            | `/platform/reports`                  | `reports.view`          |  ✅   |   ✅    |     ✅     |   ✅   |    ✅    |
| Schedules               | `/platform/schedules`                | `reports.view`          |  ✅   |   ✅    |     ✅     |   ✅   |   ❌\*   |
| Templates               | `/platform/templates`                | `reports.configure`     |  ✅   |   ✅    |     ❌     |   ❌   |    ❌    |
| **Admin & Settings**    |                                      |                         |       |         |            |        |          |
| Company Settings        | `/admin/settings`                    | `company_settings.view` |  ✅   |   ✅    |     ❌     |   ❌   |    ❌    |
| Security                | `/admin/security`                    | `security.configure`    |  ✅   |   ❌    |     ❌     |   ❌   |    ❌    |
| Users List              | `/admin/users`                       | `users.view`            |  ✅   |   ✅    |     ❌     |   ❌   |    ❌    |
| Create User             | `/admin/users/create`                | `users.manage`          |  ✅   |   ❌    |     ❌     |   ❌   |    ❌    |
| Licenses                | `/admin/licenses`                    | `company_settings.view` |  ✅   |   ✅    |     ❌     |   ❌   |    ❌    |
| API Keys                | `/admin/api-keys`                    | `api_keys.view`         |  ✅   |   ❌    |     ✅     |   ❌   |    ❌    |
| Audit Logs              | `/admin/audit-logs`                  | `security.view_logs`    |  ✅   |   ❌    |     ❌     |   ❌   |    ❌    |
| Monitoring              | `/admin/monitoring`                  | `monitoring.view`       |  ✅   |   ❌    |     ✅     |   ❌   |    ❌    |
| **Other**               |                                      |                         |       |         |            |        |          |
| Notifications           | `/platform/notifications`            | `notifications.view`    |  ✅   |   ✅    |     ✅     |   ✅   |    ❌    |
| Documentation           | `/docs`                              | _(Authenticated)_       |  ✅   |   ✅    |     ✅     |   ✅   |    ✅    |

_\*Note: Pages marked with (Authenticated) are accessible to any user who is logged in._

## Implementation Remediation Phase Checklist (Completed)

We have identified discrepancies between the Backend Access Control and the Frontend Routing/UI. The following fixes have been applied.

### Phase 1: Security Hardening (Routes)

- [x] **Secure `admin/monitoring`**: Apply `monitoring.view` permission check.
- [x] **Secure `admin/security`**: Apply `security.configure` permission check.
- [x] **Secure `platform/notifications`**: Apply `notifications.view` permission check.
- [x] **Fix `admin/api-keys`**: Change check from `company_settings.view` to `api_keys.view`.
- [x] **Fix `admin/audit-logs`**: Change check from `company_settings.view` to `security.view_logs`.

### Phase 3: Customer Action Security (Grid/Table)

- [x] **Secure `platform/customers/map`**: Restricted to `customers.map` (Technicians & Admins).
- [x] **Secure Customer Actions**: Gate Edit, Merge, Split, Apply Template, and Delivery buttons based on `customers.edit` and `reports.configure`.
- [x] **Secure Customer Detail Routes**: Require `customers.view` for integration details and `reports.configure` for report setup.

### Phase 2: Navigation Logic (Sidebar)

- [x] **Fix API Keys Visibility**: Check `api_keys.view` instead of `company_settings.view`.
- [x] **Fix Monitoring Visibility**: Check `monitoring.view` instead of `company_settings.view`.
- [x] **Fix Audit Logs Visibility**: Check `security.view_logs` instead of `company_settings.view`.

### Phase 4: Frontend API Gating (403 Cleanup)

- [x] **Prevent Unauthorized Integration Fetches**: Added checks for `integrations.view` in `CustomersFilters.tsx` and `useMasterCustomerList`.
- [x] **Prevent Unauthorized User Role Fetches**: Added checks for `users.view` in `CreateUserModal.tsx`.
- [x] **Prevent Unauthorized Unmapped Customer Fetches**: Added `skipUnmapped` flag to `useCustomersStore` to prevent 403s in `MasterCustomerList` for users without `customers.map`.
- [x] **Secure Dashboard Data**: Applied permission guards to all dashboard data fetching hooks to prevent 403s on load.

### Phase 5: Reports Action Security

- [x] **Secure Report Delete Actions**: Added `reports.generate` permission checks to hide delete buttons in `ReportCard` and `ReportsTable` for Viewers.
- [x] **Secure Bulk Actions**: Added `reports.generate` permission checks to hide Bulk Delete and Bulk Generate buttons in `ReportsFilters` for Viewers.
- [x] **Hide Selection Checkboxes**: Conditionally hide report selection checkboxes in `ReportCard` and `ReportsTable` for users without `reports.generate` permission.
- [x] **Viewer Reports Access**: Viewers can now only view and download reports, without access to delete, generate, or bulk selection actions.

### Phase 6: Schedules Action Security

- [x] **Hide Create Schedule Button**: Added `schedules.create` permission check to hide Create Schedule button in `SchedulesHeader` for Viewers.
- [x] **Secure Delete Actions**: Added `schedules.delete` permission checks to hide delete buttons in `ScheduleCard` and `SchedulesTable` for Viewers.
- [x] **Viewer Schedules Access**: Viewers can now only view schedules without access to create, edit, or delete actions.

### Phase 7: Templates Access Security

- [x] **Route Protection**: Templates route (`/platform/templates`) already protected with `reports.configure` permission.
- [x] **Sidebar Hidden**: Templates link in sidebar already hidden for users without `reports.configure`.
- [x] **Hide Template Navigation**: Added permission checks in `ConfigureReports` to hide "Browse Templates" and "Edit Template" buttons for users without `reports.configure`.
- [x] **Viewer Templates Access**: Viewers cannot access Templates page or navigate to it from any UI element.

### Phase 8: Notifications Action Security

- [x] **Secure Notifications Page**: Restricted management actions (Delete, Mark All, Clear Read, Bulk Select) to users with `notifications.configure`.
- [x] **Gated Sidebar/Nav**: Hidden Notifications link in `AppSidebar` and `FloatingNav` for users without `notifications.view`.
- [x] **Viewer Notifications Access**: Viewers can now only view notifications without access to management actions.

---

## Detailed Role Permissions

Below is the granular breakdown of permissions assigned to each role.

### Admin

- **Full Access** to all modules.

### Manager

- **Integrations**: View only.
- **Customers**: View and Edit.
- **Reports**: Full access (View, Configure, Generate, Download).
- **Schedules**: Full access.
- **Templates**: View only.
- **Users**: View only.
- **Company Settings**: View only.
- **Licenses**: View only.
- **Notifications**: View and Configure.
- **No Access**: API Keys, Monitoring, Security Logs, Exchange.

### Technician

- **Integrations**: Full access (View, Manage, Sync).
- **Customers**: View and Map. Cannot edit customer details.
- **Reports**: View and Download only.
- **Schedules**: View only.
- **Templates**: View only.
- **API Keys**: View and Create.
- **Monitoring**: View only.
- **Exchange**: Full access.
- **Notifications**: View only.
- **No Access**: Users, Licenses, Security Logs, Company Settings.

### Viewer

- **Customers**: View only.
- **Reports**: View and Download only.
- **Schedules**: View only.
- **Notifications**: View only.
- **No Access**: Integrations, Templates, Users, API Keys, Licenses, Monitoring, Security Logs, Exchange, Company Settings.

### Customer

- **Reports**: View and Download only (scope restricted).
- **No Access**: Integrations, Customers, Schedules, Templates, Users, API Keys, Licenses, Monitoring, Notifications, Security Logs, Exchange, Company Settings.
