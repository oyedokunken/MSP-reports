<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\IntegrationController;

Route::middleware(['secure.headers', 'throttle:5,1'])->group(function () {

    // Auth API Routes
    Route::post('/api/auth/login', [AuthController::class, 'authLogin'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::post('/api/auth/logout', [AuthController::class, 'authLogout'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    // Users API Routes
    Route::get('/api/users', [UserController::class, 'getUser'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::post('/api/users', [UserController::class, 'createUser'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::patch('/api/users/{id}/permissions', [UserController::class, 'updatePermissions'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    // Integrations API Routes
    Route::get('/api/integrations', [IntegrationController::class, 'getIntegrations'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::post('/api/integrations', [IntegrationController::class, 'createIntegration'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::get('/api/integrations/{id}/sync', [IntegrationController::class, 'triggerSync'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::get('/api/integrations/{id}/customers', [IntegrationController::class, 'getIntegrationCustomers'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::patch('/api/company/source-integration', [IntegrationController::class, 'setSourceIntegration'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    // Customer Mapping
    Route::get('/api/customers', [\App\Http\Controllers\Api\CustomerMappingController::class, 'getCustomers'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::get('/api/customers/unmapped', [\App\Http\Controllers\Api\CustomerMappingController::class, 'getUnmappedCustomers'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::post('/api/customers/map', [\App\Http\Controllers\Api\CustomerMappingController::class, 'mapCustomer'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);
    
    Route::get('/api/customers/{id}/integrations', [\App\Http\Controllers\Api\CustomerMappingController::class, 'getCustomerIntegrations'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);
    
    // Report Config
    Route::get('/api/reports', [\App\Http\Controllers\Api\ReportConfigController::class, 'getAvailableReports'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    Route::get('/api/customers/{id}/reports', [\App\Http\Controllers\Api\ReportConfigController::class, 'getCustomerReports'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    // Update customer report configuration
    Route::patch('/api/customers/{id}/reports', [\App\Http\Controllers\Api\ReportConfigController::class, 'updateCustomerReports'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);

    // Update customer report delivery destination
    Route::patch('/api/customers/{id}/report-destination', [\App\Http\Controllers\Api\ReportConfigController::class, 'updateCustomerReportDestination'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);
    
    // Manual report generation trigger
    Route::post('/api/reports/generate/{customer_id}', [\App\Http\Controllers\Api\ReportConfigController::class, 'generateReport'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(['auth:sanctum']);
});