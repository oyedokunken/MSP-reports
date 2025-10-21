<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    /**
     * Handle an incoming request.
     * 
     * Validates that the company has an active license for the requested feature.
     * 
     * Usage: Route::middleware(['auth:sanctum', 'license:integration_datto'])
     */
    public function handle(Request $request, Closure $next, string $licenseType): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = auth()->user();
        $company = $user->company;

        // Check if license enforcement is enabled for this company
        $licenseEnforcement = $company->settings['licensing']['license_enforcement'] ?? false;

        // If license enforcement is disabled (e.g., first customer with unlimited license)
        if (!$licenseEnforcement) {
            return $next($request);
        }

        // Check if company has the required license
        $hasLicense = $company->licenses()
            ->where('product_sku', $licenseType)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->exists();

        if (!$hasLicense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your company does not have an active license for this feature.',
                'required_license' => $licenseType,
            ], 403);
        }

        return $next($request);
    }
}
