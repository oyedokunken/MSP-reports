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

// All routes are automatically prefixed with /api

// Public Health Check (no auth required)
Route::get('/health', [HealthCheckController::class, 'index']);

Route::middleware(['secure.headers', 'throttle:300,1'])->group(function () {

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'authLogin']);
    Route::post('/auth/logout', [AuthController::class, 'authLogout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/auth/password/request', [AuthController::class, 'requestPasswordReset']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/auth/password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

    // Company Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/company/settings', [CompanyController::class, 'getSettings']);
        Route::patch('/company/settings', [CompanyController::class, 'updateSettings']);
        Route::patch('/company/branding', [CompanyController::class, 'updateBranding']);
        Route::post('/company/logo/upload', [CompanyController::class, 'uploadLogo']);
        Route::delete('/company/logo/delete', [CompanyController::class, 'deleteLogo']);
    });

    // Users Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users', [UserController::class, 'getUser']);
        Route::post('/users', [UserController::class, 'createUser']);
        Route::patch('/users/{id}', [UserController::class, 'updateUser']);
        Route::patch('/users/{id}/permissions', [UserController::class, 'updatePermissions']);
        Route::patch('/users/{id}/deactivate', [UserController::class, 'deactivateUser']);
        Route::patch('/users/{id}/reactivate', [UserController::class, 'reactivateUser']);
        Route::delete('/users/{id}', [UserController::class, 'deleteUser']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetUserPassword']);
    });

    // Integrations Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/integrations', [IntegrationController::class, 'getIntegrations']);
        Route::get('/integrations/config/{integrationType}', [IntegrationController::class, 'getIntegrationTypeConfig']);
        Route::post('/integrations', [IntegrationController::class, 'createIntegration']);
        Route::get('/integrations/{id}/sync', [IntegrationController::class, 'triggerSync']);
        Route::get('/integrations/{id}/customers', [IntegrationController::class, 'getIntegrationCustomers']);
        Route::patch('/integrations/{id}', [IntegrationController::class, 'updateIntegration']);
        Route::delete('/integrations/{id}', [IntegrationController::class, 'deleteIntegration']);
        Route::get('/integrations/{id}/sync-logs', [IntegrationController::class, 'getSyncLogs']);
        Route::get('/integrations/{id}/health', [IntegrationController::class, 'healthCheck']);
        Route::get('/integrations/{id}/rate-limit', [IntegrationController::class, 'getRateLimitStatus']);
        Route::post('/integrations/{id}/historical-import', [IntegrationController::class, 'startHistoricalImport']);
        Route::get('/integrations/{id}/historical-import-jobs', [IntegrationController::class, 'getHistoricalImportJobs']);
        Route::patch('/company/source-integration', [IntegrationController::class, 'setSourceIntegration']);
        
        // Integration Preview Routes
        Route::get('/integrations/{integration}/preview/template', [IntegrationPreviewController::class, 'getTemplate']);
        Route::get('/integrations/{integration}/preview/data', [IntegrationPreviewController::class, 'getPreviewData']);
        Route::get('/integrations/{integration}/preview', [IntegrationPreviewController::class, 'getPreview']);
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
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/customers', [CustomerMappingController::class, 'getCustomers']);
        Route::get('/customers/unmapped', [CustomerMappingController::class, 'getUnmappedCustomers']);
        Route::post('/customers/map', [CustomerMappingController::class, 'mapCustomer']);
        Route::get('/customers/{id}/integrations', [CustomerMappingController::class, 'getCustomerIntegrations']);
        Route::get('/customers/{customerId}/integrations/{integrationCustomerId}/stats', [CustomerMappingController::class, 'getCustomerIntegrationStats']);
        Route::patch('/customers/{id}', [CustomerMappingController::class, 'updateCustomer']);
        Route::post('/customers/{id}/merge', [CustomerMappingController::class, 'mergeCustomers']);
        Route::post('/customers/{id}/split', [CustomerMappingController::class, 'splitCustomer']);
        Route::delete('/customers/{customerId}/mappings/{mappingId}', [CustomerMappingController::class, 'deleteMapping']);
    });

    // Report Configuration Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/reports', [ReportConfigController::class, 'getAvailableReports']);
        Route::get('/customers/{id}/available-reports', [ReportConfigController::class, 'getAvailableReportsForCustomer']);
        Route::get('/customers/{id}/reports', [ReportConfigController::class, 'getCustomerReports']);
        Route::patch('/customers/{id}/reports', [ReportConfigController::class, 'updateCustomerReports']);
        Route::patch('/customers/{id}/report-destination', [ReportConfigController::class, 'updateCustomerReportDestination']);
        Route::post('/reports/generate/{customer_id}', [ReportConfigController::class, 'generateReport']);
    });

    // Reporting & Analytics Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/reports/scheduled', [ReportAnalyticsController::class, 'getScheduledReports']);
        Route::get('/reports/history', [ReportAnalyticsController::class, 'getReportHistory']);
        Route::get('/reports/{id}/download', [ReportAnalyticsController::class, 'downloadReport']);
        Route::post('/reports/{id}/email', [ReportAnalyticsController::class, 'emailReport']);
        Route::post('/reports/{id}/share', [ReportAnalyticsController::class, 'generateShareLink']);
        Route::delete('/reports/{id}', [ReportAnalyticsController::class, 'deleteReport']);
        Route::delete('/reports/schedules/{id}', [ReportAnalyticsController::class, 'deleteSchedule']);
    });

    // Public shared report download (no auth required)
    Route::get('/reports/shared/{token}', [ReportAnalyticsController::class, 'downloadSharedReport']);

    // License Management Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/licenses', [LicenseController::class, 'index']);
        Route::get('/licenses/usage', [LicenseController::class, 'getLicenseUsage']);
        Route::post('/licenses/check/integration', [LicenseController::class, 'checkIntegrationLicense']);
        Route::post('/licenses/check/retention', [LicenseController::class, 'checkRetentionLicense']);
        Route::post('/licenses/check/user', [LicenseController::class, 'checkUserLicense']);
    });

    // Monitoring & Health Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/monitoring/status', [HealthCheckController::class, 'status']);
        Route::get('/monitoring/integrations', [HealthCheckController::class, 'integrationMetrics']);
        Route::get('/monitoring/reports', [HealthCheckController::class, 'reportMetrics']);
        Route::get('/monitoring/queue', [HealthCheckController::class, 'queueMetrics']);
    });
});
