<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     * 
     * Validates that the authenticated user has the required permission
     * for the requested resource/action.
     * 
     * Usage: Route::middleware(['auth:sanctum', 'permission:integrations.manage'])
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = auth()->user();

        // Parse permission (e.g., "integrations.manage" -> ["integrations", "manage"])
        $parts = explode('.', $permission);
        
        if (count($parts) !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid permission format.',
            ], 500);
        }

        [$resource, $action] = $parts;

        // Check if user has the permission
        if (!$this->hasPermission($user, $resource, $action)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user has a specific permission.
     */
    private function hasPermission($user, string $resource, string $action): bool
    {
        $permissions = $user->permissions ?? [];

        // Check if resource exists in permissions
        if (!isset($permissions[$resource])) {
            return false;
        }

        // Check if action is allowed
        return $permissions[$resource][$action] ?? false;
    }
}
