<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCompanyScope
{
    /**
     * Handle an incoming request.
     * 
     * Ensures that the authenticated user's company context is set
     * and validates that all requests are properly scoped to their company.
     * 
     * This is CRITICAL for multi-tenancy security.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce for authenticated requests
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Ensure user has a company_id
        if (!$user->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not associated with a company.',
            ], 403);
        }

        // Check if company is active
        if ($user->company && in_array($user->company->status, ['suspended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company account is suspended. Please contact support.',
            ], 403);
        }

        // Store company_id in request for easy access
        $request->merge(['_company_id' => $user->company_id]);

        return $next($request);
    }
}
