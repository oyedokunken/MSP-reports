<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\IntegrationPreviewController;
use App\Http\Controllers\Api\CustomerMappingController;
use App\Http\Controllers\Api\ReportConfigController;
use App\Http\Controllers\Api\ReportAnalyticsController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ActiveDirectoryController;
use App\Http\Controllers\Api\ExchangeController;
use App\Http\Controllers\Admin\ITGlueReportController;
use App\Http\Controllers\Api\DeliveryConfigController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\DashboardController;

// All routes are automatically prefixed with /api prefix

// Public Health Check (no auth required to implement)
Route::get('/health', [HealthCheckController::class, 'index']);
Route::get('/health/database', [HealthCheckController::class, 'database']);
Route::get('/health/redis', [HealthCheckController::class, 'redis']);
Route::get('/health/email', [HealthCheckController::class, 'email']);
Route::get('/health/scheduler', [HealthCheckController::class, 'scheduler']);
Route::get('/health/storage', [HealthCheckController::class, 'storage']);
Route::get('/health/reports', [HealthCheckController::class, 'reports']);
Route::get('/health/external', [HealthCheckController::class, 'external']);

// Public SAML Metadata (no auth required)
Route::get('/saml/metadata', [AuthController::class, 'getSamlMetadata']);

// Public auth config endpoint (tells frontend which auth methods are enabled)
Route::get('/auth/config', [AuthController::class, 'getAuthConfig']);

Route::middleware(['secure.headers', 'throttle:300,1'])->group(function () {

    // Auth Routes - Stricter rate limiting for security-sensitive endpoints
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('/auth/login', [AuthController::class, 'authLogin']);
        Route::post('/auth/password/request', [AuthController::class, 'requestPasswordReset']);
        Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
    });

    // OAuth & SAML Routes (Session Enabled for State/PKCE)
    Route::middleware([\Illuminate\Session\Middleware\StartSession::class])->group(function () {
        // Microsoft
        Route::get('/auth/microsoft/redirect', [AuthController::class, 'redirectToMicrosoft']);
        Route::get('/auth/microsoft/callback', [AuthController::class, 'handleMicrosoftCallback']);

        // Keycloak OIDC
        Route::get('/auth/keycloak/redirect', [AuthController::class, 'redirectToKeycloak']);
        Route::get('/auth/keycloak/callback', [AuthController::class, 'handleKeycloakCallback']);

        // SAML
        Route::get('/auth/saml/login', [AuthController::class, 'initiateSamlLogin']);
        Route::post('/auth/saml/acs', [AuthController::class, 'handleSamlAcs']);
        Route::get('/auth/saml/sls', [AuthController::class, 'handleSamlSls']);
        Route::post('/auth/saml/sls', [AuthController::class, 'handleSamlSls']);
    });

    Route::post('/auth/logout', [AuthController::class, 'authLogout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/auth/password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword'])->middleware('auth:sanctum');

    // Company Routes
    Route::middleware(['auth:sanctum', 'session.timeout', 'scope.customers'])->group(function () {
        Route::get('/company/settings', [CompanyController::class, 'getSettings']);
        Route::patch('/company/settings', [CompanyController::class, 'updateSettings']);
        Route::patch('/company/branding', [CompanyController::class, 'updateBranding']);
        Route::post('/company/logo/upload', [CompanyController::class, 'uploadLogo']);
        Route::delete('/company/logo/delete', [CompanyController::class, 'deleteLogo']);
        Route::get('/company/report-naming', [CompanyController::class, 'getReportNaming']);
        Route::patch('/company/report-naming', [CompanyController::class, 'updateReportNaming']);
    });

    // Users Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/users', [UserController::class, 'getUser'])->middleware('permission:users,view');
        Route::get('/users/roles', [UserController::class, 'getRoles'])->middleware('permission:users,view');
        Route::post('/users', [UserController::class, 'createUser'])->middleware('permission:users,manage');
        Route::patch('/users/{id}', [UserController::class, 'updateUser'])->middleware('permission:users,manage');
        Route::patch('/users/{id}/permissions', [UserController::class, 'updatePermissions'])->middleware('permission:users,manage');
        Route::patch('/users/{id}/deactivate', [UserController::class, 'deactivateUser'])->middleware('permission:users,manage');
        Route::patch('/users/{id}/reactivate', [UserController::class, 'reactivateUser'])->middleware('permission:users,manage');
        Route::delete('/users/{id}', [UserController::class, 'deleteUser'])->middleware('permission:users,manage');
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetUserPassword'])->middleware('permission:users,manage');
        Route::post('/users/{id}/profile-picture', [UserController::class, 'uploadProfilePicture'])->middleware('permission:users,manage');
        Route::delete('/users/{id}/profile-picture', [UserController::class, 'deleteProfilePicture'])->middleware('permission:users,manage');
        Route::post('/users/force-password-change', [UserController::class, 'clearForcePasswordChange']);
    });

    // Integrations Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/integrations', [IntegrationController::class, 'getIntegrations'])->middleware('permission:integrations,view');
        Route::get('/integrations/config/{integrationType}', [IntegrationController::class, 'getIntegrationTypeConfig'])->middleware('permission:integrations,manage');
        Route::post('/integrations', [IntegrationController::class, 'createIntegration'])->middleware('permission:integrations,manage');
        Route::get('/integrations/{id}/sync', [IntegrationController::class, 'triggerSync'])->middleware('permission:integrations,sync');
        Route::get('/integrations/{id}/customers', [IntegrationController::class, 'getIntegrationCustomers'])->middleware('permission:integrations,view');
        Route::patch('/integrations/{id}', [IntegrationController::class, 'updateIntegration'])->middleware('permission:integrations,manage');
        Route::delete('/integrations/{id}', [IntegrationController::class, 'deleteIntegration'])->middleware('permission:integrations,manage');
        Route::get('/integrations/{id}/sync-logs', [IntegrationController::class, 'getSyncLogs'])->middleware('permission:integrations,view');
        Route::get('/integrations/{id}/health', [IntegrationController::class, 'healthCheck'])->middleware('permission:integrations,view');
        Route::get('/integrations/{id}/rate-limit', [IntegrationController::class, 'getRateLimitStatus'])->middleware('permission:integrations,view');
        Route::post('/integrations/{id}/historical-import', [IntegrationController::class, 'startHistoricalImport'])->middleware('permission:integrations,sync');
        Route::get('/integrations/{id}/historical-import-jobs', [IntegrationController::class, 'getHistoricalImportJobs'])->middleware('permission:integrations,sync');
        Route::patch('/company/source-integration', [IntegrationController::class, 'setSourceIntegration'])->middleware('permission:integrations,manage');

        // SharePoint Routes
        Route::get('/integrations/{id}/sharepoint/browse', [IntegrationController::class, 'browseSharePoint'])->middleware('permission:integrations,view');
        Route::post('/integrations/{id}/sharepoint/folders', [IntegrationController::class, 'createSharePointFolder'])->middleware('permission:integrations,manage');

        // Integration Preview Routes
        Route::get('/integrations/{integration}/preview/template', [IntegrationPreviewController::class, 'getTemplate'])->middleware('permission:integrations,view');
        Route::get('/integrations/{integration}/preview/data', [IntegrationPreviewController::class, 'getPreviewData'])->middleware('permission:integrations,view');
        Route::get('/integrations/{integration}/preview', [IntegrationPreviewController::class, 'getPreview'])->middleware('permission:integrations,view');
        Route::get('/integrations/{integration}/customer-licenses', [IntegrationPreviewController::class, 'getCustomerLicenses'])->middleware('permission:integrations,view');
    });

    // Active Directory Push Routes (from agent)
    Route::middleware([\App\Http\Middleware\ApiKeyAuthentication::class])->group(function () {
        Route::post('/integrations/active-directory/push', [ActiveDirectoryController::class, 'push']);
        Route::get('/integrations/active-directory/status', [ActiveDirectoryController::class, 'status']);
    });

    // Exchange Push Routes (from agent)
    Route::middleware([\App\Http\Middleware\ApiKeyAuthentication::class])->group(function () {
        Route::post('/integrations/exchange/push', [ExchangeController::class, 'push']);
        Route::get('/integrations/exchange/status', [ExchangeController::class, 'status']);
    });

    // Customer Mapping Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/customers', [CustomerMappingController::class, 'getCustomers'])->middleware('permission:customers,view');
        Route::get('/customers/all-ids', [CustomerMappingController::class, 'getAllCustomerIds'])->middleware('permission:customers,view');
        Route::get('/customers/unmapped', [CustomerMappingController::class, 'getUnmappedCustomers'])->middleware('permission:customers,map');
        Route::post('/customers/static', [CustomerMappingController::class, 'createStaticCustomer'])->middleware('permission:customers,map');
        Route::post('/customers/map', [CustomerMappingController::class, 'mapCustomer'])->middleware('permission:customers,map');
        Route::get('/customers/{id}/integrations', [CustomerMappingController::class, 'getCustomerIntegrations'])->middleware('permission:customers,view');
        Route::get('/customers/{customerId}/integrations/{integrationCustomerId}/stats', [CustomerMappingController::class, 'getCustomerIntegrationStats'])->middleware('permission:customers,view');
        Route::patch('/customers/{id}', [CustomerMappingController::class, 'updateCustomer'])->middleware('permission:customers,edit');
        Route::post('/customers/{id}/merge', [CustomerMappingController::class, 'mergeCustomers'])->middleware('permission:customers,edit');
        Route::post('/customers/{id}/split', [CustomerMappingController::class, 'splitCustomer'])->middleware('permission:customers,edit');
        Route::delete('/customers/{customerId}/mappings/{mappingId}', [CustomerMappingController::class, 'deleteMapping'])->middleware('permission:customers,edit');

        // Smart matching with embeddings
        Route::get('/customers/embedding-status', [CustomerMappingController::class, 'getEmbeddingStatus'])->middleware('permission:customers,map');
        Route::post('/customers/smart-match', [CustomerMappingController::class, 'getSmartMatchSuggestions'])->middleware('permission:customers,map');
        Route::post('/customers/batch-auto-match', [CustomerMappingController::class, 'batchAutoMatch'])->middleware('permission:customers,map');
    });

    // Report Configuration Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/reports', [ReportConfigController::class, 'getAvailableReports'])->middleware('permission:reports,view');
        Route::get('/customers/{id}/available-reports', [ReportConfigController::class, 'getAvailableReportsForCustomer'])->middleware('permission:reports,view');
        Route::get('/customers/{id}/reports', [ReportConfigController::class, 'getCustomerReports'])->middleware('permission:reports,view');
        Route::patch('/customers/{id}/reports', [ReportConfigController::class, 'updateCustomerReports'])->middleware('permission:reports,configure');
        Route::patch('/customers/{id}/report-destination', [ReportConfigController::class, 'updateCustomerReportDestination'])->middleware('permission:reports,configure');
        Route::post('/reports/generate/{customer_id}', [ReportConfigController::class, 'generateReport'])->middleware('permission:reports,generate');
        Route::post('/reports/bulk-generate', [ReportConfigController::class, 'bulkGenerateReports'])->middleware('permission:reports,generate');
        Route::post('/reports/schedules/{schedule_id}/generate', [ReportConfigController::class, 'generateFromSchedule'])->middleware('permission:reports,generate');
        Route::patch('/reports/schedules/{schedule_id}', [ReportConfigController::class, 'updateSchedule'])->middleware('permission:reports,configure');
        // Resetting schedule is restricted to those who can configure
        Route::post('/reports/schedules/{schedule_id}/reset', [ReportConfigController::class, 'resetSchedule'])->middleware('permission:reports,configure');
    });

    // Delivery Configuration Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/delivery/methods', [DeliveryConfigController::class, 'getAvailableMethods'])->middleware('permission:reports,view');
        Route::get('/customers/{id}/delivery', [DeliveryConfigController::class, 'getCustomerDeliveryConfig'])->middleware('permission:reports,view');
        Route::patch('/customers/{id}/delivery', [DeliveryConfigController::class, 'updateCustomerDeliveryConfig'])->middleware('permission:reports,configure');
        Route::post('/customers/{id}/delivery/test', [DeliveryConfigController::class, 'testConnection'])->middleware('permission:reports,configure');
    });

    // Reporting & Analytics Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/reports/scheduled', [ReportAnalyticsController::class, 'getScheduledReports'])->middleware('permission:reports,view');
        Route::get('/reports/history', [ReportAnalyticsController::class, 'getReportHistory'])->middleware('permission:reports,view');
        Route::get('/reports/{id}/download', [ReportAnalyticsController::class, 'downloadReport'])->middleware('permission:reports,download');
        Route::post('/reports/{id}/email', [ReportAnalyticsController::class, 'emailReport'])->middleware('permission:reports,generate');
        Route::post('/reports/{id}/deliver', [ReportAnalyticsController::class, 'deliverReport'])->middleware('permission:reports,generate');
        Route::get('/reports/{id}/email-preview', [ReportAnalyticsController::class, 'previewEmail'])->middleware('permission:reports,view');
        Route::post('/reports/{id}/share', [ReportAnalyticsController::class, 'generateShareLink'])->middleware('permission:reports,view');
        Route::post('/reports/batch-discard', [ReportAnalyticsController::class, 'discardReports'])->middleware('permission:reports,generate');
        Route::delete('/reports', [ReportAnalyticsController::class, 'batchDelete'])->middleware('permission:reports,generate');
        Route::delete('/reports/{id}', [ReportAnalyticsController::class, 'deleteReport'])->middleware('permission:reports,generate');
        Route::delete('/reports/schedules/{id}', [ReportAnalyticsController::class, 'deleteSchedule'])->middleware('permission:reports,configure');

        // ITGlue Reports
        Route::get('/reports/itglue/license-consumption', [ITGlueReportController::class, 'downloadLicenseReport'])->middleware('permission:reports,download');
    });

    // Public shared report download (no auth required) - Rate limited to prevent abuse
    Route::middleware(['throttle:30,1'])->group(function () {
        Route::get('/reports/shared/{token}', [ReportAnalyticsController::class, 'downloadSharedReport']);
        Route::get('/reports/librenms/devices/preview', [App\Http\Controllers\Admin\LibreNMSReportController::class, 'previewDeviceReport']);
        Route::get('/reports/librenms/devices', [App\Http\Controllers\Admin\LibreNMSReportController::class, 'downloadDeviceReport']);
    });

    // API Key Management Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/api-keys', [ApiKeyController::class, 'index'])->middleware('permission:api_keys,view');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->middleware('permission:api_keys,create');
        Route::post('/api-keys/{id}/revoke', [ApiKeyController::class, 'revoke'])->middleware('permission:api_keys,revoke');
        Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy'])->middleware('permission:api_keys,revoke');
    });

    // License Management Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/licenses', [LicenseController::class, 'index'])->middleware('permission:licenses,view');
        Route::get('/licenses/usage', [LicenseController::class, 'getLicenseUsage'])->middleware('permission:licenses,view');
        Route::post('/licenses/check/integration', [LicenseController::class, 'checkIntegrationLicense'])->middleware('permission:licenses,manage');
        Route::post('/licenses/check/retention', [LicenseController::class, 'checkRetentionLicense'])->middleware('permission:licenses,manage');
        Route::post('/licenses/check/user', [LicenseController::class, 'checkUserLicense'])->middleware('permission:licenses,manage');
    });

    // Report Templates Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/report-templates/all-report-types', [\App\Http\Controllers\Api\ReportTemplateController::class, 'getAllReportTypes'])->middleware('permission:templates,view');
        Route::get('/report-templates/available-for-customer', [\App\Http\Controllers\Api\ReportTemplateController::class, 'getAvailableReportTypes'])->middleware('permission:templates,view');
        Route::post('/report-templates/{template}/preview', [\App\Http\Controllers\Api\ReportTemplateController::class, 'preview'])->middleware('permission:templates,view');
        Route::apiResource('report-templates', \App\Http\Controllers\Api\ReportTemplateController::class)->parameters([
            'report-templates' => 'template'
        ]); // Note: apiResource would need internal mapping, but usually restricted to manager+
        Route::post('/report-templates/{template}/apply-to-customer', [\App\Http\Controllers\Api\ReportTemplateController::class, 'applyToCustomer'])->middleware('permission:templates,edit');
        Route::post('/report-templates/{template}/apply-to-customers', [\App\Http\Controllers\Api\ReportTemplateController::class, 'applyToCustomers'])->middleware('permission:templates,edit');
        Route::get('/report-templates/{template}/applied-customers', [\App\Http\Controllers\Api\ReportTemplateController::class, 'getAppliedCustomers'])->middleware('permission:templates,view');
    });

    // Notification Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index'])->middleware('permission:notifications,view');
        Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->middleware('permission:notifications,view');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->middleware('permission:notifications,view');
        Route::delete('/notifications/read', [\App\Http\Controllers\Api\NotificationController::class, 'deleteAllRead'])->middleware('permission:notifications,view');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])->middleware('permission:notifications,view');
    });

    // Dashboard Routes - Optimized aggregated endpoint
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/dashboard/summary', [DashboardController::class, 'getSummary']);
    });

    // Monitoring & Health Routes
    Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
        Route::get('/monitoring/status', [HealthCheckController::class, 'status'])->middleware('permission:monitoring,view');
        Route::get('/monitoring/integrations', [HealthCheckController::class, 'integrationMetrics'])->middleware('permission:monitoring,view');
        Route::get('/monitoring/reports', [HealthCheckController::class, 'reportMetrics'])->middleware('permission:monitoring,view');
        Route::get('/monitoring/reports/monthly', [HealthCheckController::class, 'monthlyReportAnalytics'])->middleware('permission:monitoring,view');
        Route::get('/monitoring/queue', [HealthCheckController::class, 'queueMetrics'])->middleware('permission:monitoring,view');
        Route::get('/monitoring/activity-trends', [HealthCheckController::class, 'activityTrends'])->middleware('permission:monitoring,view');

        // Audit Log Routes
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:security,view_logs');
        Route::get('/audit-logs/stats', [AuditLogController::class, 'stats'])->middleware('permission:security,view_logs');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->middleware('permission:security,view_logs');
    });
});
