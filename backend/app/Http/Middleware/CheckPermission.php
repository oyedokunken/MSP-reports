<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RolePermissionService;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $resource, string $action): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Get user's effective permissions (role defaults merged with custom permissions)
        $roleName = $user->role->name ?? 'Viewer';
        $effectivePermissions = RolePermissionService::mergePermissions($roleName, $user->permissions);

        // Check if user has the required permission
        if (!RolePermissionService::hasPermission($effectivePermissions, $resource, $action)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to perform this action',
                'required_permission' => [
                    'resource' => $resource,
                    'action' => $action,
                ]
            ], 403);
        }

        // Add effective permissions to request for use in controllers
        $request->merge(['effective_permissions' => $effectivePermissions]);

        return $next($request);
    }
}
