<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissions
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = auth()->user();

        
        $parts = explode('.', $permission);
        
        if (count($parts) !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid permission format.',
            ], 500);
        }

        [$resource, $action] = $parts;

        
        if (!$this->hasPermission($user, $resource, $action)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }

    private function hasPermission(\App\Models\User $user, string $resource, string $action): bool
    {
        $permissions = $user->permissions ?? [];

        
        if (!isset($permissions[$resource])) {
            return false;
        }

        
        return $permissions[$resource][$action] ?? false;
    }
}
