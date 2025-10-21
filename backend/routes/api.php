<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\CustomerMappingController;
use App\Http\Controllers\Api\ReportConfigController;
use App\Http\Controllers\Api\ReportAnalyticsController;

// All routes are automatically prefixed with /api
// No CSRF protection needed for API routes
Route::middleware(['secure.headers', 'throttle:60,1'])->group(function () {

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'authLogin']);
    Route::post('/auth/logout', [AuthController::class, 'authLogout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/auth/password/request', [AuthController::class, 'requestPasswordReset']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/auth/password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

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
        Route::post('/integrations', [IntegrationController::class, 'createIntegration']);
        Route::get('/integrations/{id}/sync', [IntegrationController::class, 'triggerSync']);
        Route::get('/integrations/{id}/customers', [IntegrationController::class, 'getIntegrationCustomers']);
        Route::patch('/integrations/{id}', [IntegrationController::class, 'updateIntegration']);
        Route::delete('/integrations/{id}', [IntegrationController::class, 'deleteIntegration']);
        Route::get('/integrations/{id}/sync-logs', [IntegrationController::class, 'getSyncLogs']);
        Route::get('/integrations/{id}/health', [IntegrationController::class, 'healthCheck']);
        Route::get('/integrations/{id}/rate-limit', [IntegrationController::class, 'getRateLimitStatus']);
        Route::post('/integrations/{id}/historical-import', [IntegrationController::class, 'startHistoricalImport']);
        Route::patch('/company/source-integration', [IntegrationController::class, 'setSourceIntegration']);
    });

    // Customer Mapping Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/customers', [CustomerMappingController::class, 'getCustomers']);
        Route::get('/customers/unmapped', [CustomerMappingController::class, 'getUnmappedCustomers']);
        Route::post('/customers/map', [CustomerMappingController::class, 'mapCustomer']);
        Route::get('/customers/{id}/integrations', [CustomerMappingController::class, 'getCustomerIntegrations']);
        Route::patch('/customers/{id}', [CustomerMappingController::class, 'updateCustomer']);
        Route::post('/customers/{id}/merge', [CustomerMappingController::class, 'mergeCustomers']);
        Route::post('/customers/{id}/split', [CustomerMappingController::class, 'splitCustomer']);
        Route::post('/customers/bulk-map', [CustomerMappingController::class, 'bulkMapCustomers']);
        Route::delete('/customers/{customerId}/mappings/{mappingId}', [CustomerMappingController::class, 'deleteMapping']);
    });

    // Report Configuration Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/reports', [ReportConfigController::class, 'getAvailableReports']);
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
    });
});
