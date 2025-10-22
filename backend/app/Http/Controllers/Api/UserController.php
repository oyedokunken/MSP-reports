<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\AuditLog;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $authUser = $request->user();

        $users = User::where('company_id', $authUser->company_id)
                     ->with('role')
                     ->get()
                     
                     ->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    public function createUser(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,locked',
            'auth_method' => 'sometimes|in:local,saml,m365,ldap',
            'permissions' => 'nullable|array',
        ]);

        
        $validated['name'] = trim(strip_tags($validated['name']));
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['auth_method'] = trim($validated['auth_method'] ?? 'local');
        $validated['status'] = trim($validated['status'] ?? 'active');

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'] ?? [];
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user = User::create([
            'company_id' => $authUser->company_id,
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'auth_method' => $validated['auth_method'],
            'permissions' => $permissions,
        ]);

        
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ], 201);
    }

    public function updatePermissions(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        $validated = $request->validate([
            'permissions' => 'required|array'
        ]);

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'];
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user->permissions = $permissions;
        $user->save();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_permissions_updated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
                'permissions' => $permissions,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->user_id . ',user_id',
            'role_id' => 'sometimes|exists:roles,id',
        ]);

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
        ];

        
        if (isset($validated['name'])) {
            $user->name = trim(strip_tags($validated['name']));
        }
        
        if (isset($validated['email'])) {
            $user->email = trim(strtolower($validated['email']));
        }
        
        if (isset($validated['role_id'])) {
            $user->role_id = $validated['role_id'];
        }

        $user->save();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_updated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'old_data' => $oldData,
                'new_data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function deactivateUser(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        
        if ($user->user_id === $authUser->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot deactivate your own account'
            ], 400);
        }

        $user->status = 'inactive';
        $user->save();

        
        $user->tokens()->delete();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_deactivated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'User deactivated successfully',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function reactivateUser(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        $user->status = 'active';
        $user->save();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_reactivated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'User reactivated successfully',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        
        if ($user->user_id === $authUser->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        
        $user->tokens()->delete();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_deleted',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
                'user_name' => $user->name,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }

    public function resetUserPassword(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        
        if ($user->auth_method !== 'local') {
            return response()->json([
                'status' => 'error',
                'message' => 'Password reset is only available for local authentication users'
            ], 400);
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        
        $user->tokens()->delete();

        
        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_password_reset_by_admin',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User password reset successfully'
        ]);
    }
}
