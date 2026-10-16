# API Reference

This reference is generated from `backend/routes/api.php` and the Form Request classes in `backend/app/Http/Requests`. Regenerate it whenever routes or validation rules change.

## Conventions

- **Base URL:** `{APP_URL}/api`.
- **Content type:** JSON request and response bodies; send `Accept: application/json`. File uploads use `multipart/form-data`.
- **Bearer token:** a Laravel Sanctum personal access token from `POST /api/auth/login` or an SSO callback, sent as `Authorization: Bearer <token>`.
- **API key:** a company API key created under `/api/api-keys`, sent as `Authorization: Bearer <key>`. Accepted only on the agent push endpoints. Keys are stored as SHA-256 hashes.
- **Authorisation:** routes with a permission require `<resource>.<action>` in the caller's effective permissions (role defaults from `RolePermissionService` merged with per-user overrides).
- **Session timeout:** authenticated routes enforce the `session.timeout` idle limit.
- **Rate limits:** 300 requests per minute on the main group, 10 per minute on `/auth/login` and the password reset endpoints, 30 per minute on public report downloads.
- **Pagination:** list endpoints accept `page` and `per_page`, and return the page metadata (`current_page`, `last_page`, `per_page`, `total`) alongside `data`.
- **Errors:** validation failures return `422` with `{ "message": "...", "errors": { "field": ["..."] } }`. Authentication failures return `401`, authorisation failures `403`. Other failures return a `message` field; some controllers wrap it as `{ "status": "error" }` and others as `{ "success": false }`.

## Authentication flow

```bash
curl -s -X POST "$APP_URL/api/auth/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"<password>","auth_method":"local"}'
```

The response contains the authenticated `user`, their `company`, the Sanctum `token`, and a `force_password_change` flag. Send the token on subsequent requests:

```bash
curl -s "$APP_URL/api/auth/me" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

SSO flows (`/auth/microsoft/*`, `/auth/keycloak/*`, `/auth/saml/*`) redirect the browser to the identity provider and return to `FRONTEND_URL` with the issued token.

## Endpoint index

- [Public Health Check](#public-health-check)
- [Auth Routes](#auth-routes)
- [OAuth & SAML Routes](#oauth--saml-routes)
- [Company Routes](#company-routes)
- [Users Routes](#users-routes)
- [Integrations Routes](#integrations-routes)
- [Active Directory Push Routes](#active-directory-push-routes)
- [Exchange Push Routes](#exchange-push-routes)
- [Customer Mapping Routes](#customer-mapping-routes)
- [Report Configuration Routes](#report-configuration-routes)
- [Delivery Configuration Routes](#delivery-configuration-routes)
- [Reporting & Analytics Routes](#reporting--analytics-routes)
- [Public shared report download](#public-shared-report-download)
- [API Key Management Routes](#api-key-management-routes)
- [License Management Routes](#license-management-routes)
- [Report Templates Routes](#report-templates-routes)
- [Notification Routes](#notification-routes)
- [Dashboard Routes](#dashboard-routes)
- [Monitoring & Health Routes](#monitoring--health-routes)
- [Audit Log Routes](#audit-log-routes)

## Public Health Check

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/health` | None | n/a | `HealthCheckController@index` |
| `GET` | `/api/health/database` | None | n/a | `HealthCheckController@database` |
| `GET` | `/api/health/redis` | None | n/a | `HealthCheckController@redis` |
| `GET` | `/api/health/email` | None | n/a | `HealthCheckController@email` |
| `GET` | `/api/health/scheduler` | None | n/a | `HealthCheckController@scheduler` |
| `GET` | `/api/health/storage` | None | n/a | `HealthCheckController@storage` |
| `GET` | `/api/health/reports` | None | n/a | `HealthCheckController@reports` |
| `GET` | `/api/health/external` | None | n/a | `HealthCheckController@external` |
| `GET` | `/api/saml/metadata` | None | n/a | `AuthController@getSamlMetadata` |
| `GET` | `/api/auth/config` | None | n/a | `AuthController@getAuthConfig` |

## Auth Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `POST` | `/api/auth/login` | None | n/a | `AuthController@authLogin` |
| `POST` | `/api/auth/password/request` | None | n/a | `AuthController@requestPasswordReset` |
| `POST` | `/api/auth/password/reset` | None | n/a | `AuthController@resetPassword` |

### Request parameters

`POST /api/auth/login` validated by `LoginRequest`:

| Field | Rules |
|---|---|
| `email` | required, email |
| `password` | nullable, string |
| `auth_method` | required, in:local,saml,m365,ldap,keycloak,oidc |

`POST /api/auth/password/request` validated by `RequestPasswordResetRequest`:

| Field | Rules |
|---|---|
| `email` | required, email |

`POST /api/auth/password/reset` validated by `ResetPasswordRequest`:

| Field | Rules |
|---|---|
| `email` | required, email |
| `token` | required, string |
| `password` | required, string, confirmed + custom rule |

## OAuth & SAML Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/auth/microsoft/redirect` | None | n/a | `AuthController@redirectToMicrosoft` |
| `GET` | `/api/auth/microsoft/callback` | None | n/a | `AuthController@handleMicrosoftCallback` |
| `GET` | `/api/auth/keycloak/redirect` | None | n/a | `AuthController@redirectToKeycloak` |
| `GET` | `/api/auth/keycloak/callback` | None | n/a | `AuthController@handleKeycloakCallback` |
| `GET` | `/api/auth/saml/login` | None | n/a | `AuthController@initiateSamlLogin` |
| `POST` | `/api/auth/saml/acs` | None | n/a | `AuthController@handleSamlAcs` |
| `GET` | `/api/auth/saml/sls` | None | n/a | `AuthController@handleSamlSls` |
| `POST` | `/api/auth/saml/sls` | None | n/a | `AuthController@handleSamlSls` |
| `POST` | `/api/auth/logout` | Bearer token | n/a | `AuthController@authLogout` |
| `GET` | `/api/auth/me` | Bearer token | n/a | `AuthController@me` |
| `POST` | `/api/auth/password/change` | Bearer token | n/a | `AuthController@changePassword` |
| `POST` | `/api/auth/verify-password` | Bearer token | n/a | `AuthController@verifyPassword` |

### Request parameters

`GET /api/auth/microsoft/callback` validated by `OAuthCallbackRequest`:

| Field | Rules |
|---|---|
| `code` | sometimes, string |
| `state` | sometimes, string |
| `error` | sometimes, string |
| `error_description` | sometimes, string |

`GET /api/auth/keycloak/callback` validated by `OAuthCallbackRequest`:

| Field | Rules |
|---|---|
| `code` | sometimes, string |
| `state` | sometimes, string |
| `error` | sometimes, string |
| `error_description` | sometimes, string |

`POST /api/auth/saml/acs` validated by `SamlAcsRequest`:

| Field | Rules |
|---|---|
| `SAMLResponse` | required, string |
| `RelayState` | sometimes, string |

`GET /api/auth/saml/sls` validated by `SamlSlsRequest`:

| Field | Rules |
|---|---|
| `SAMLRequest` | sometimes, string |
| `SAMLResponse` | sometimes, string |
| `RelayState` | sometimes, string |
| `SigAlg` | sometimes, string |
| `Signature` | sometimes, string |

`POST /api/auth/saml/sls` validated by `SamlSlsRequest`:

| Field | Rules |
|---|---|
| `SAMLRequest` | sometimes, string |
| `SAMLResponse` | sometimes, string |
| `RelayState` | sometimes, string |
| `SigAlg` | sometimes, string |
| `Signature` | sometimes, string |

`POST /api/auth/password/change` validated by `ChangePasswordRequest`:

| Field | Rules |
|---|---|
| `current_password` | required, string |
| `new_password` | required, string, confirmed + custom rule |

## Company Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/company/settings` | Bearer token | n/a | `CompanyController@getSettings` |
| `PATCH` | `/api/company/settings` | Bearer token | n/a | `CompanyController@updateSettings` |
| `PATCH` | `/api/company/branding` | Bearer token | n/a | `CompanyController@updateBranding` |
| `POST` | `/api/company/logo/upload` | Bearer token | n/a | `CompanyController@uploadLogo` |
| `DELETE` | `/api/company/logo/delete` | Bearer token | n/a | `CompanyController@deleteLogo` |
| `GET` | `/api/company/report-naming` | Bearer token | n/a | `CompanyController@getReportNaming` |
| `PATCH` | `/api/company/report-naming` | Bearer token | n/a | `CompanyController@updateReportNaming` |

### Request parameters

`PATCH /api/company/settings` validated by `UpdateSettingsRequest`:

| Field | Rules |
|---|---|
| `name` | sometimes, string, max:255 |
| `abn` | sometimes, nullable, string, max:50 |
| `contact_email` | sometimes, nullable, email, max:255 |
| `contact_phone` | sometimes, nullable, string, max:50 |
| `address` | sometimes, nullable, string, max:1000 |
| `settings` | sometimes, nullable, array |

`PATCH /api/company/branding` validated by `UpdateBrandingRequest`:

| Field | Rules |
|---|---|
| `company_name` | sometimes, string, max:255 |
| `primary_color` | sometimes, string, regex:/^#[0-9a-f]{6}$/i |
| `secondary_color` | sometimes, string, regex:/^#[0-9a-f]{6}$/i |
| `logo_url` | sometimes, nullable, url, max:500 |
| `favicon_url` | sometimes, nullable, url, max:500 |
| `email_from_address` | sometimes, nullable, email, max:255 |
| `email_from_name` | sometimes, nullable, string, max:255 |
| `support_email` | sometimes, nullable, email, max:255 |
| `support_phone` | sometimes, nullable, string, max:50 |
| `footer_text` | sometimes, nullable, string, max:500 |
| `report_header_text` | sometimes, nullable, string, max:255 |
| `smtp_host` | sometimes, nullable, string, max:255 |
| `smtp_port` | sometimes, nullable, integer |
| `smtp_encryption` | sometimes, nullable, string, in:tls,ssl,none |
| `smtp_username` | sometimes, nullable, string, max:255 |
| `smtp_password` | sometimes, nullable, string, max:255 |

`POST /api/company/logo/upload` validated by `UploadLogoRequest`:

| Field | Rules |
|---|---|
| `logo` | required, image, mimes:jpeg,jpg,png,svg,webp, max:5120 |
| `type` | required, in:company,profile |

## Users Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/users` | Bearer token | `users.view` | `UserController@getUser` |
| `GET` | `/api/users/roles` | Bearer token | `users.view` | `UserController@getRoles` |
| `POST` | `/api/users` | Bearer token | `users.manage` | `UserController@createUser` |
| `PATCH` | `/api/users/{id}` | Bearer token | `users.manage` | `UserController@updateUser` |
| `PATCH` | `/api/users/{id}/permissions` | Bearer token | `users.manage` | `UserController@updatePermissions` |
| `PATCH` | `/api/users/{id}/deactivate` | Bearer token | `users.manage` | `UserController@deactivateUser` |
| `PATCH` | `/api/users/{id}/reactivate` | Bearer token | `users.manage` | `UserController@reactivateUser` |
| `DELETE` | `/api/users/{id}` | Bearer token | `users.manage` | `UserController@deleteUser` |
| `POST` | `/api/users/{id}/reset-password` | Bearer token | `users.manage` | `UserController@resetUserPassword` |
| `POST` | `/api/users/{id}/profile-picture` | Bearer token | `users.manage` | `UserController@uploadProfilePicture` |
| `DELETE` | `/api/users/{id}/profile-picture` | Bearer token | `users.manage` | `UserController@deleteProfilePicture` |
| `POST` | `/api/users/force-password-change` | Bearer token | n/a | `UserController@clearForcePasswordChange` |

### Request parameters

`GET /api/users` validated by `GetUsersRequest`:

| Field | Rules |
|---|---|
| `per_page` | sometimes, integer, min:1, max:100 |
| `page` | sometimes, integer, min:1 |
| `search` | sometimes, nullable, string, max:255 |
| `role_id` | sometimes, nullable, integer, exists:roles,id |

`POST /api/users` validated by `CreateUserRequest`:

| Field | Rules |
|---|---|
| `name` | required, string, max:255 |
| `email` | required, email, unique:users,email |
| `password` | required, string, min:6 |
| `role_id` | required, exists:roles,id |
| `status` | sometimes, in:active,inactive,locked |
| `auth_method` | sometimes, in:local,saml,m365,ldap |
| `permissions` | nullable, array |
| `scoped_customers` | nullable, array |
| `force_password_change` | sometimes, boolean |

`PATCH /api/users/{id}` validated by `UpdateUserRequest`:

| Field | Rules |
|---|---|
| `name` | sometimes, string, max:255 |
| `email` | sometimes, email, unique:users,email |
| `role_id` | sometimes, exists:roles,id |
| `scoped_customers` | nullable, array |

`PATCH /api/users/{id}/permissions` validated by `UpdatePermissionsRequest`:

| Field | Rules |
|---|---|
| `permissions` | required, array |

`POST /api/users/{id}/reset-password` validated by `ResetUserPasswordRequest`:

| Field | Rules |
|---|---|
| `new_password` | required, string + custom rule |

`POST /api/users/{id}/profile-picture` validated by `UploadProfilePictureRequest`:

| Field | Rules |
|---|---|
| `profile_picture` | required, image, mimes:jpeg,png,jpg,gif,webp, max:5120 |

`POST /api/users/force-password-change` validated by `ClearForcePasswordChangeRequest`:

| Field | Rules |
|---|---|
| `current_password` | required, string |
| `new_password` | required, string, confirmed + custom rule |

## Integrations Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/integrations` | Bearer token | `integrations.view` | `IntegrationController@getIntegrations` |
| `GET` | `/api/integrations/config/{integrationType}` | Bearer token | `integrations.manage` | `IntegrationController@getIntegrationTypeConfig` |
| `POST` | `/api/integrations` | Bearer token | `integrations.manage` | `IntegrationController@createIntegration` |
| `GET` | `/api/integrations/{id}/sync` | Bearer token | `integrations.sync` | `IntegrationController@triggerSync` |
| `GET` | `/api/integrations/{id}/customers` | Bearer token | `integrations.view` | `IntegrationController@getIntegrationCustomers` |
| `PATCH` | `/api/integrations/{id}` | Bearer token | `integrations.manage` | `IntegrationController@updateIntegration` |
| `DELETE` | `/api/integrations/{id}` | Bearer token | `integrations.manage` | `IntegrationController@deleteIntegration` |
| `GET` | `/api/integrations/{id}/sync-logs` | Bearer token | `integrations.view` | `IntegrationController@getSyncLogs` |
| `GET` | `/api/integrations/{id}/health` | Bearer token | `integrations.view` | `IntegrationController@healthCheck` |
| `GET` | `/api/integrations/{id}/rate-limit` | Bearer token | `integrations.view` | `IntegrationController@getRateLimitStatus` |
| `POST` | `/api/integrations/{id}/historical-import` | Bearer token | `integrations.sync` | `IntegrationController@startHistoricalImport` |
| `DELETE` | `/api/integrations/{id}/historical-import/dismiss` | Bearer token | `integrations.sync` | `IntegrationController@dismissHistoricalImportFailure` |
| `GET` | `/api/integrations/{id}/historical-import-jobs` | Bearer token | `integrations.sync` | `IntegrationController@getHistoricalImportJobs` |
| `PATCH` | `/api/company/source-integration` | Bearer token | `integrations.manage` | `IntegrationController@setSourceIntegration` |
| `GET` | `/api/integrations/{id}/sharepoint/browse` | Bearer token | `integrations.view` | `IntegrationController@browseSharePoint` |
| `POST` | `/api/integrations/{id}/sharepoint/folders` | Bearer token | `integrations.manage` | `IntegrationController@createSharePointFolder` |
| `GET` | `/api/integrations/{integration}/preview/template` | Bearer token | `integrations.view` | `IntegrationPreviewController@getTemplate` |
| `GET` | `/api/integrations/{integration}/preview/data` | Bearer token | `integrations.view` | `IntegrationPreviewController@getPreviewData` |
| `GET` | `/api/integrations/{integration}/preview` | Bearer token | `integrations.view` | `IntegrationPreviewController@getPreview` |
| `GET` | `/api/integrations/{integration}/customer-licenses` | Bearer token | `integrations.view` | `IntegrationPreviewController@getCustomerLicenses` |

### Request parameters

`PATCH /api/integrations/{id}` validated by `UpdateIntegrationRequest`:

| Field | Rules |
|---|---|
| `display_name` | sometimes, string, min:2, max:255, regex:/^[A-Za-z0-9\s\-\_\.]+$/ |
| `status` | sometimes, string, active, disconnected, error, importing_historical + custom rule |
| `credentials` | sometimes, array |
| `configuration` | sometimes, array |

`POST /api/integrations/{id}/historical-import` validated by `StartHistoricalImportRequest`:

| Field | Rules |
|---|---|
| `start_date` | required, date, before:today |
| `end_date` | required, date, after:start_date, before_or_equal:today |

`PATCH /api/company/source-integration` validated by `SetSourceOfTruthRequest`:

| Field | Rules |
|---|---|
| `integration_id` | required, integer, exists:integrations,integration_id |

`POST /api/integrations/{id}/sharepoint/folders` validated by `CreateSharePointFolderRequest`:

| Field | Rules |
|---|---|
| `path` | nullable, string |
| `name` | required, string, min:1 |

`GET /api/integrations/{integration}/preview/data` validated by `GetIntegrationPreviewRequest`:

| Field | Rules |
|---|---|
| `customer_id` | nullable, string |

`GET /api/integrations/{integration}/preview` validated by `GetIntegrationPreviewRequest`:

| Field | Rules |
|---|---|
| `customer_id` | nullable, string |

`GET /api/integrations/{integration}/customer-licenses` validated by `GetIntegrationPreviewRequest`:

| Field | Rules |
|---|---|
| `customer_id` | nullable, string |

## Active Directory Push Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `POST` | `/api/integrations/active-directory/push` | API key | n/a | `ActiveDirectoryController@push` |
| `GET` | `/api/integrations/active-directory/status` | API key | n/a | `ActiveDirectoryController@status` |

### Request parameters

`POST /api/integrations/active-directory/push` validated by `ActiveDirectoryPushRequest`:

| Field | Rules |
|---|---|
| `syncType` | required, in:full,delta |
| `syncTimestamp` | required, date |
| `domain` | required, string, max:255 |
| `data` | required, array |
| `data.users` | present, array |
| `data.securityGroups` | present, array |
| `data.groupMemberships` | present, array |
| `data.licenseAssignments` | nullable, array |
| `statistics` | required, array |
| `statistics.userCount` | required, integer |
| `statistics.groupCount` | required, integer |
| `statistics.membershipCount` | required, integer |

## Exchange Push Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `POST` | `/api/integrations/exchange/push` | API key | n/a | `ExchangeController@push` |
| `GET` | `/api/integrations/exchange/status` | API key | n/a | `ExchangeController@status` |

### Request parameters

`POST /api/integrations/exchange/push` validated by `ExchangePushRequest`:

| Field | Rules |
|---|---|
| `syncType` | required, in:full,delta |
| `syncTimestamp` | required, date |
| `exchangeOrganization` | nullable, string |
| `exchangeVersion` | nullable, string |
| `data` | required, array |
| `data.mailboxes` | required, array |
| `data.emailAliases` | nullable, array |
| `data.permissions` | nullable, array |
| `data.statistics` | nullable, array |
| `statistics` | nullable, array |

## Customer Mapping Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/customers` | Bearer token | `customers.view` | `CustomerMappingController@getCustomers` |
| `GET` | `/api/customers/all-ids` | Bearer token | `customers.view` | `CustomerMappingController@getAllCustomerIds` |
| `GET` | `/api/customers/unmapped` | Bearer token | `customers.map` | `CustomerMappingController@getUnmappedCustomers` |
| `POST` | `/api/customers/static` | Bearer token | `customers.map` | `CustomerMappingController@createStaticCustomer` |
| `POST` | `/api/customers/map` | Bearer token | `customers.map` | `CustomerMappingController@mapCustomer` |
| `GET` | `/api/customers/{id}/integrations` | Bearer token | `customers.view` | `CustomerMappingController@getCustomerIntegrations` |
| `GET` | `/api/customers/{customerId}/integrations/{integrationCustomerId}/stats` | Bearer token | `customers.view` | `CustomerMappingController@getCustomerIntegrationStats` |
| `PATCH` | `/api/customers/{id}` | Bearer token | `customers.edit` | `CustomerMappingController@updateCustomer` |
| `POST` | `/api/customers/{id}/merge` | Bearer token | `customers.edit` | `CustomerMappingController@mergeCustomers` |
| `POST` | `/api/customers/{id}/split` | Bearer token | `customers.edit` | `CustomerMappingController@splitCustomer` |
| `DELETE` | `/api/customers/{customerId}/mappings/{mappingId}` | Bearer token | `customers.edit` | `CustomerMappingController@deleteMapping` |
| `GET` | `/api/customers/embedding-status` | Bearer token | `customers.map` | `CustomerMappingController@getEmbeddingStatus` |
| `POST` | `/api/customers/smart-match` | Bearer token | `customers.map` | `CustomerMappingController@getSmartMatchSuggestions` |
| `POST` | `/api/customers/batch-auto-match` | Bearer token | `customers.map` | `CustomerMappingController@batchAutoMatch` |

### Request parameters

`POST /api/customers/static` validated by `CreateStaticCustomerRequest`:

| Field | Rules |
|---|---|
| `canonical_name` | required, string, max:255 |
| `status` | sometimes, in:active,inactive |
| `report_destination` | sometimes, email, nullable |
| `metadata` | sometimes, array |

`POST /api/customers/map` validated by `MapCustomerRequest`:

| Field | Rules |
|---|---|
| `integration_customer_id` | required, integer, exists:integration_customers,integration_customer_id |
| `create_new` | sometimes, boolean |

`PATCH /api/customers/{id}` validated by `UpdateCustomerRequest`:

| Field | Rules |
|---|---|
| `canonical_name` | sometimes, string, max:255 |
| `status` | sometimes, in:active,inactive |
| `report_destination` | sometimes, email, nullable |
| `metadata` | sometimes, array |

`POST /api/customers/{id}/merge` validated by `MergeCustomersRequest`:

| Field | Rules |
|---|---|
| `merge_customer_id` | required, integer, exists:customers,customer_id |

`POST /api/customers/{id}/split` validated by `SplitCustomerRequest`:

| Field | Rules |
|---|---|
| `new_customer_name` | required, string, max:255 |
| `integration_customer_ids` | required, array, min:1 |
| `integration_customer_ids.*` | integer, exists:integration_customers,integration_customer_id |

`POST /api/customers/smart-match` validated by `GetSmartMatchSuggestionsRequest`:

| Field | Rules |
|---|---|
| `integration_customer_ids` | required, array, min:1, max:50 |
| `integration_customer_ids.*` | integer |
| `use_agent` | sometimes, boolean |

`POST /api/customers/batch-auto-match` validated by `BatchAutoMatchRequest`:

| Field | Rules |
|---|---|
| `integration_customer_ids` | sometimes, array, max:100 |
| `integration_customer_ids.*` | integer |
| `auto_apply` | sometimes, boolean |
| `min_confidence` | sometimes, integer, min:50, max:100 |

## Report Configuration Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/reports` | Bearer token | `reports.view` | `ReportConfigController@getAvailableReports` |
| `GET` | `/api/customers/{id}/available-reports` | Bearer token | `reports.view` | `ReportConfigController@getAvailableReportsForCustomer` |
| `GET` | `/api/customers/{id}/reports` | Bearer token | `reports.view` | `ReportConfigController@getCustomerReports` |
| `PATCH` | `/api/customers/{id}/reports` | Bearer token | `reports.configure` | `ReportConfigController@updateCustomerReports` |
| `PATCH` | `/api/customers/{id}/report-destination` | Bearer token | `reports.configure` | `ReportConfigController@updateCustomerReportDestination` |
| `POST` | `/api/reports/generate/{customer_id}` | Bearer token | `reports.generate` | `ReportConfigController@generateReport` |
| `POST` | `/api/reports/bulk-generate` | Bearer token | `reports.generate` | `ReportConfigController@bulkGenerateReports` |
| `POST` | `/api/reports/schedules/{schedule_id}/generate` | Bearer token | `reports.generate` | `ReportConfigController@generateFromSchedule` |
| `PATCH` | `/api/reports/schedules/{schedule_id}` | Bearer token | `reports.configure` | `ReportConfigController@updateSchedule` |
| `POST` | `/api/reports/schedules/{schedule_id}/reset` | Bearer token | `reports.configure` | `ReportConfigController@resetSchedule` |

### Request parameters

`GET /api/customers/{id}/available-reports` validated by `GetAvailableReportsForCustomerRequest`:

| Field | Rules |
|---|---|
| `frequency` | sometimes, string, in:daily,weekly,monthly,yearly,on-demand |

`PATCH /api/customers/{id}/reports` validated by `UpdateCustomerReportsRequest`:

| Field | Rules |
|---|---|
| `schedules` | required, array, min:1 |
| `schedules.*.report_type` | required, string |
| `schedules.*.frequency` | required, string, in:daily,weekly,monthly,yearly,on-demand |
| `schedules.*.time_of_day` | nullable, string |
| `schedules.*.active` | nullable, boolean |
| `schedules.*.schedule_config` | nullable, array |

`PATCH /api/customers/{id}/report-destination` validated by `UpdateReportDestinationRequest`:

| Field | Rules |
|---|---|
| `method` | required, string, in:email,api,download |
| `destination` | required, string, email |
| `cc` | nullable, array |
| `cc.*` | nullable, email |

`POST /api/reports/generate/{customer_id}` validated by `GenerateReportRequest`:

| Field | Rules |
|---|---|
| `report_type` | nullable, string |
| `start_date` | nullable, date |
| `end_date` | nullable, date, after_or_equal:start_date |
| `frequency` | nullable, string, in:daily,weekly,monthly,yearly,on-demand |
| `preview_only` | nullable, boolean |

`POST /api/reports/bulk-generate` validated by `BulkGenerateReportsRequest`:

| Field | Rules |
|---|---|
| `customer_ids` | required, array, min:1, max:100 |
| `customer_ids.*` | required, integer |
| `report_type` | nullable, string |
| `frequency` | nullable, string, in:daily,weekly,monthly,yearly |
| `start_date` | nullable, date |
| `end_date` | nullable, date, after_or_equal:start_date |
| `preview_only` | nullable, boolean |

`POST /api/reports/schedules/{schedule_id}/generate` validated by `GenerateReportRequest`:

| Field | Rules |
|---|---|
| `report_type` | nullable, string |
| `start_date` | nullable, date |
| `end_date` | nullable, date, after_or_equal:start_date |
| `frequency` | nullable, string, in:daily,weekly,monthly,yearly,on-demand |
| `preview_only` | nullable, boolean |

`PATCH /api/reports/schedules/{schedule_id}` validated by `UpdateScheduleRequest`:

| Field | Rules |
|---|---|
| `report_type` | nullable, string |
| `frequency` | nullable, string, in:daily,weekly,monthly,yearly,on-demand |
| `time_of_day` | nullable, string |
| `active` | nullable, boolean |
| `schedule_config` | nullable, array |

## Delivery Configuration Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/delivery/methods` | Bearer token | `reports.view` | `DeliveryConfigController@getAvailableMethods` |
| `GET` | `/api/customers/{id}/delivery` | Bearer token | `reports.view` | `DeliveryConfigController@getCustomerDeliveryConfig` |
| `PATCH` | `/api/customers/{id}/delivery` | Bearer token | `reports.configure` | `DeliveryConfigController@updateCustomerDeliveryConfig` |
| `POST` | `/api/customers/{id}/delivery/test` | Bearer token | `reports.configure` | `DeliveryConfigController@testConnection` |

### Request parameters

`PATCH /api/customers/{id}/delivery` validated by `UpdateDeliveryConfigRequest`:

| Field | Rules |
|---|---|
| `enabled_methods` | required, array, min:1 |
| `enabled_methods.*` | string |
| `email` | nullable, array |
| `email.destination` | nullable, email |
| `email.cc` | nullable, array |
| `email.cc.*` | nullable, email |
| `sharepoint` | nullable, array |
| `sharepoint.tenant_id` | nullable, string |
| `sharepoint.client_id` | nullable, string |
| `sharepoint.client_secret` | nullable, string |
| `sharepoint.site_id` | nullable, string |
| `sharepoint.drive_id` | nullable, string |
| `sharepoint.folder_path` | nullable, string |

`POST /api/customers/{id}/delivery/test` validated by `TestDeliveryConnectionRequest`:

| Field | Rules |
|---|---|
| `method` | required, string |
| `config` | required, array |

## Reporting & Analytics Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/reports/scheduled` | Bearer token | `reports.view` | `ReportAnalyticsController@getScheduledReports` |
| `GET` | `/api/reports/history` | Bearer token | `reports.view` | `ReportAnalyticsController@getReportHistory` |
| `GET` | `/api/reports/{id}/download` | Bearer token | `reports.download` | `ReportAnalyticsController@downloadReport` |
| `POST` | `/api/reports/{id}/email` | Bearer token | `reports.generate` | `ReportAnalyticsController@emailReport` |
| `POST` | `/api/reports/{id}/deliver` | Bearer token | `reports.generate` | `ReportAnalyticsController@deliverReport` |
| `GET` | `/api/reports/{id}/email-preview` | Bearer token | `reports.view` | `ReportAnalyticsController@previewEmail` |
| `POST` | `/api/reports/{id}/share` | Bearer token | `reports.view` | `ReportAnalyticsController@generateShareLink` |
| `POST` | `/api/reports/batch-discard` | Bearer token | `reports.generate` | `ReportAnalyticsController@discardReports` |
| `DELETE` | `/api/reports` | Bearer token | `reports.generate` | `ReportAnalyticsController@batchDelete` |
| `DELETE` | `/api/reports/{id}` | Bearer token | `reports.generate` | `ReportAnalyticsController@deleteReport` |
| `DELETE` | `/api/reports/schedules/{id}` | Bearer token | `reports.configure` | `ReportAnalyticsController@deleteSchedule` |
| `GET` | `/api/reports/itglue/license-consumption` | Bearer token | `reports.download` | `ITGlueReportController@downloadLicenseReport` |

### Request parameters

`POST /api/reports/{id}/email` validated by `EmailReportRequest`:

| Field | Rules |
|---|---|
| `emails` | required, string |

`POST /api/reports/batch-discard` validated by `DiscardReportsRequest`:

| Field | Rules |
|---|---|
| `report_ids` | nullable, array |
| `report_ids.*` | integer |

`DELETE /api/reports` validated by `BatchDeleteReportsRequest`:

| Field | Rules |
|---|---|
| `report_ids` | required, array |
| `report_ids.*` | exists:generated_reports,generated_report_id |

## Public shared report download

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/reports/shared/{token}` | None | n/a | `ReportAnalyticsController@downloadSharedReport` |
| `GET` | `/api/reports/librenms/devices/preview` | None | n/a | `LibreNMSReportController@previewDeviceReport` |
| `GET` | `/api/reports/librenms/devices` | None | n/a | `LibreNMSReportController@downloadDeviceReport` |

## API Key Management Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/api-keys` | Bearer token | `api_keys.view` | `ApiKeyController@index` |
| `POST` | `/api/api-keys` | Bearer token | `api_keys.create` | `ApiKeyController@store` |
| `POST` | `/api/api-keys/{id}/revoke` | Bearer token | `api_keys.revoke` | `ApiKeyController@revoke` |
| `DELETE` | `/api/api-keys/{id}` | Bearer token | `api_keys.revoke` | `ApiKeyController@destroy` |

### Request parameters

`GET /api/api-keys` validated by `GetApiKeysRequest`:

| Field | Rules |
|---|---|
| `search` | sometimes, nullable, string, max:100 |
| `customer_id` | sometimes, nullable, integer, exists:customers,customer_id |

`POST /api/api-keys` validated by `StoreApiKeyRequest`:

| Field | Rules |
|---|---|
| `display_name` | required, string, max:255 |
| `customer_id` | nullable, integer, exists:customers,customer_id |
| `permissions` | nullable, array |
| `ip_whitelist` | nullable, array |
| `ip_whitelist.*` | ip |
| `allowed_endpoints` | nullable, array |
| `allowed_endpoints.*` | string |

## License Management Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/licenses` | Bearer token | `licenses.view` | `LicenseController@index` |
| `GET` | `/api/licenses/usage` | Bearer token | `licenses.view` | `LicenseController@getLicenseUsage` |
| `POST` | `/api/licenses/check/integration` | Bearer token | `licenses.manage` | `LicenseController@checkIntegrationLicense` |
| `POST` | `/api/licenses/check/retention` | Bearer token | `licenses.manage` | `LicenseController@checkRetentionLicense` |
| `POST` | `/api/licenses/check/user` | Bearer token | `licenses.manage` | `LicenseController@checkUserLicense` |

### Request parameters

`POST /api/licenses/check/integration` validated by `CheckIntegrationLicenseRequest`:

| Field | Rules |
|---|---|
| `integration_type` | required, string |

`POST /api/licenses/check/retention` validated by `CheckRetentionLicenseRequest`:

| Field | Rules |
|---|---|
| `requested_months` | required, integer, min:1 |

## Report Templates Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/report-templates/all-report-types` | Bearer token | `templates.view` | `ReportTemplateController@getAllReportTypes` |
| `GET` | `/api/report-templates/available-for-customer` | Bearer token | `templates.view` | `ReportTemplateController@getAvailableReportTypes` |
| `POST` | `/api/report-templates/{template}/preview` | Bearer token | `templates.view` | `ReportTemplateController@preview` |
| `GET` | `/api/report-templates` | Bearer token | none (see notes) | `ReportTemplateController@index` |
| `POST` | `/api/report-templates` | Bearer token | none (see notes) | `ReportTemplateController@store` |
| `GET` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@show` |
| `PUT/PATCH` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@update` |
| `DELETE` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@destroy` |
| `GET` | `/api/report-templates` | Bearer token | none (see notes) | `ReportTemplateController@index` |
| `POST` | `/api/report-templates` | Bearer token | none (see notes) | `ReportTemplateController@store` |
| `GET` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@show` |
| `PUT/PATCH` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@update` |
| `DELETE` | `/api/report-templates/{template}` | Bearer token | none (see notes) | `ReportTemplateController@destroy` |
| `POST` | `/api/report-templates/{template}/apply-to-customer` | Bearer token | `templates.edit` | `ReportTemplateController@applyToCustomer` |
| `POST` | `/api/report-templates/{template}/apply-to-customers` | Bearer token | `templates.edit` | `ReportTemplateController@applyToCustomers` |
| `GET` | `/api/report-templates/{template}/applied-customers` | Bearer token | `templates.view` | `ReportTemplateController@getAppliedCustomers` |

### Request parameters

`GET /api/report-templates/available-for-customer` validated by `GetAvailableReportTypesRequest`:

| Field | Rules |
|---|---|
| `customer_id` | required, integer, exists:customers,customer_id |

`GET /api/report-templates` validated by `GetReportTemplatesRequest`:

| Field | Rules |
|---|---|
| `report_type` | nullable, string |
| `active` | nullable, string, in:true,false,1,0 |
| `global` | nullable, string, in:true,false,1,0 |

`POST /api/report-templates` validated by `StoreReportTemplateRequest`:

| Field | Rules |
|---|---|
| `customer_id` | nullable, integer, exists:customers,customer_id |
| `template_name` | required, string, max:255 |
| `description` | nullable, string |
| `report_type` | required, string |
| `frequency` | required, in:daily,weekly,monthly,yearly,on-demand |
| `schedule_config` | nullable, array |
| `time_of_day` | nullable, string, date_format:H:i |
| `timezone` | nullable, string |
| `included_sections` | nullable, array |
| `delivery_settings` | nullable, array |
| `report_options` | nullable, array |
| `active` | boolean |

`PUT/PATCH /api/report-templates/{template}` validated by `UpdateReportTemplateRequest`:

| Field | Rules |
|---|---|
| `template_name` | string, max:255 |
| `description` | nullable, string |
| `report_type` | string |
| `frequency` | in:daily,weekly,monthly,yearly,on-demand |
| `schedule_config` | nullable, array |
| `time_of_day` | nullable, string, date_format:H:i |
| `timezone` | nullable, string |
| `included_sections` | nullable, array |
| `delivery_settings` | nullable, array |
| `report_options` | nullable, array |
| `active` | boolean |
| `customer_id` | sometimes, integer, exists:customers,customer_id |

`GET /api/report-templates` validated by `GetReportTemplatesRequest`:

| Field | Rules |
|---|---|
| `report_type` | nullable, string |
| `active` | nullable, string, in:true,false,1,0 |
| `global` | nullable, string, in:true,false,1,0 |

`POST /api/report-templates` validated by `StoreReportTemplateRequest`:

| Field | Rules |
|---|---|
| `customer_id` | nullable, integer, exists:customers,customer_id |
| `template_name` | required, string, max:255 |
| `description` | nullable, string |
| `report_type` | required, string |
| `frequency` | required, in:daily,weekly,monthly,yearly,on-demand |
| `schedule_config` | nullable, array |
| `time_of_day` | nullable, string, date_format:H:i |
| `timezone` | nullable, string |
| `included_sections` | nullable, array |
| `delivery_settings` | nullable, array |
| `report_options` | nullable, array |
| `active` | boolean |

`PUT/PATCH /api/report-templates/{template}` validated by `UpdateReportTemplateRequest`:

| Field | Rules |
|---|---|
| `template_name` | string, max:255 |
| `description` | nullable, string |
| `report_type` | string |
| `frequency` | in:daily,weekly,monthly,yearly,on-demand |
| `schedule_config` | nullable, array |
| `time_of_day` | nullable, string, date_format:H:i |
| `timezone` | nullable, string |
| `included_sections` | nullable, array |
| `delivery_settings` | nullable, array |
| `report_options` | nullable, array |
| `active` | boolean |
| `customer_id` | sometimes, integer, exists:customers,customer_id |

`POST /api/report-templates/{template}/apply-to-customer` validated by `ApplyTemplateRequest`:

| Field | Rules |
|---|---|
| `customer_id` | required, exists:customers,customer_id |

`POST /api/report-templates/{template}/apply-to-customers` validated by `ApplyToCustomersRequest`:

| Field | Rules |
|---|---|
| `customer_ids` | required, array |
| `customer_ids.*` | exists:customers,customer_id |

## Notification Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/notifications` | Bearer token | `notifications.view` | `NotificationController@index` |
| `PATCH` | `/api/notifications/{id}/read` | Bearer token | `notifications.view` | `NotificationController@markAsRead` |
| `POST` | `/api/notifications/mark-all-read` | Bearer token | `notifications.view` | `NotificationController@markAllAsRead` |
| `DELETE` | `/api/notifications/read` | Bearer token | `notifications.view` | `NotificationController@deleteAllRead` |
| `DELETE` | `/api/notifications/{id}` | Bearer token | `notifications.view` | `NotificationController@destroy` |

### Request parameters

`GET /api/notifications` validated by `GetNotificationsRequest`:

| Field | Rules |
|---|---|
| `per_page` | nullable, integer, min:1, max:100 |

## Dashboard Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/dashboard/summary` | Bearer token | n/a | `DashboardController@getSummary` |

## Monitoring & Health Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/monitoring/status` | Bearer token | `monitoring.view` | `HealthCheckController@status` |
| `GET` | `/api/monitoring/integrations` | Bearer token | `monitoring.view` | `HealthCheckController@integrationMetrics` |
| `GET` | `/api/monitoring/reports` | Bearer token | `monitoring.view` | `HealthCheckController@reportMetrics` |
| `GET` | `/api/monitoring/reports/monthly` | Bearer token | `monitoring.view` | `HealthCheckController@monthlyReportAnalytics` |
| `GET` | `/api/monitoring/queue` | Bearer token | `monitoring.view` | `HealthCheckController@queueMetrics` |
| `GET` | `/api/monitoring/activity-trends` | Bearer token | `monitoring.view` | `HealthCheckController@activityTrends` |

### Request parameters

`GET /api/monitoring/integrations` validated by `GetMetricsRequest`:

| Field | Rules |
|---|---|
| `days` | nullable, integer, min:1, max:365 |
| `months` | nullable, integer, min:1, max:24 |

`GET /api/monitoring/reports` validated by `GetMetricsRequest`:

| Field | Rules |
|---|---|
| `days` | nullable, integer, min:1, max:365 |
| `months` | nullable, integer, min:1, max:24 |

`GET /api/monitoring/reports/monthly` validated by `GetMetricsRequest`:

| Field | Rules |
|---|---|
| `days` | nullable, integer, min:1, max:365 |
| `months` | nullable, integer, min:1, max:24 |

`GET /api/monitoring/activity-trends` validated by `GetMetricsRequest`:

| Field | Rules |
|---|---|
| `days` | nullable, integer, min:1, max:365 |
| `months` | nullable, integer, min:1, max:24 |

## Audit Log Routes

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| `GET` | `/api/audit-logs` | Bearer token | `security.view_logs` | `AuditLogController@index` |
| `GET` | `/api/audit-logs/stats` | Bearer token | `security.view_logs` | `AuditLogController@stats` |
| `GET` | `/api/audit-logs/export` | Bearer token | `security.view_logs` | `AuditLogController@export` |

### Request parameters

`GET /api/audit-logs` validated by `GetAuditLogsRequest`:

| Field | Rules |
|---|---|
| `per_page` | nullable, integer, min:1, max:100 |
| `search` | nullable, string, max:255 |
| `event_type` | nullable, string, max:255 |
| `actor_type` | nullable, string, max:255 |
| `target_type` | nullable, string, max:255 |
| `date_from` | nullable, date |
| `date_to` | nullable, date |

`GET /api/audit-logs/export` validated by `GetAuditLogsRequest`:

| Field | Rules |
|---|---|
| `per_page` | nullable, integer, min:1, max:100 |
| `search` | nullable, string, max:255 |
| `event_type` | nullable, string, max:255 |
| `actor_type` | nullable, string, max:255 |
| `target_type` | nullable, string, max:255 |
| `date_from` | nullable, date |
| `date_to` | nullable, date |

## Notes and known gaps

Found during the October 2026 review and listed so integrators are not surprised:

- The `report-templates` resource routes (`index`, `store`, `show`, `update`, `destroy`) carry no `permission:` middleware.
- `GET /api/reports/librenms/devices` and `/devices/preview` sit outside the authenticated group.
- `GET /api/integrations/{id}/sync` changes state; treat it as non-idempotent.
- The public `/api/health/*` endpoints are intended for load balancers and should be restricted at the proxy in production.
