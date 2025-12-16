<?php

use Illuminate\Support\Facades\Route;

/**
 * Web Routes
 * 
 * This application is API-only. All routes are defined in routes/api.php
 * Web routes are not used.
 */

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Reporting Platform backend is running',
    ]);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Session\Middleware\AuthenticateSession::class,
]);

// Temporary test route for branding partials (Phase 1)
// TODO: Remove this route after Phase 1 testing is complete
Route::get('/test-branding', function () {
    $branding = [
        'company_name' => 'Territory Technology Solutions',
        'company_logo' => null,
        'customer_logo' => null,
        'primary_color' => '#1a56db',
        'secondary_color' => '#7c3aed',
        'report_title' => 'Monthly Service Report',
        'footer_text' => '© 2025 Territory Technology Solutions',
        'footer_notes' => 'Confidential - For Internal Use Only',
        'support_email' => 'support@tts.com.au',
        'support_phone' => '1300 XXX XXX',
    ];
    
    $customer = (object)[
        'canonical_name' => 'Test Customer Pty Ltd',
    ];
    
    return view('reports.partials.test-preview', [
        'branding' => $branding,
        'customer' => $customer,
        'reportDate' => now()->format('F j, Y'),
    ]);
});