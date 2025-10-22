<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
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

        
        $licenseEnforcement = $company->settings['licensing']['license_enforcement'] ?? false;

        
        if (!$licenseEnforcement) {
            return $next($request);
        }

        
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
