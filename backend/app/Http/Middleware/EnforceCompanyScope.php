<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        
        if (!$user->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not associated with a company.',
            ], 403);
        }

        
        if (in_array($user->company->status, ['suspended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company account is suspended. Please contact support.',
            ], 403);
        }

        
        $request->merge(['_company_id' => $user->company_id]);

        return $next($request);
    }
}
