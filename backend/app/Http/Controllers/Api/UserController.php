<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\LicenseChecker;
use App\Services\RolePermissionService;
use App\Rules\StrongPassword;
use App\Mail\WelcomeUserMail;

class UserController extends Controller
{
    public function getUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = User::where('company_id', $authUser->company_id)
                     ->with('role');

        // Get paginated results
        $users = $query->paginate($perPage, ['*'], 'page', $page);

        // Hide password from all users in the collection
        $users->getCollection()->transform(function ($user) {
            return $user->makeHidden(['password']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    public function createUser(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        // Check user license before creating
        $licenseCheck = $licenseChecker->canCreateUser($authUser->company);
        
        if (!$licenseCheck['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => $licenseCheck['reason'],
                'license_status' => $licenseChecker->getUserLicenseStatus($authUser->company)
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,locked',
            'auth_method' => 'sometimes|in:local,saml,m365,ldap',
            'permissions' => 'nullable|array',
            'scoped_customers' => 'nullable|array',
            'force_password_change' => 'sometimes|boolean',
        ]);
        
        // Validate scoped_customers separately to provide better error messages
        if (isset($validated['scoped_customers']) && is_array($validated['scoped_customers'])) {
            $invalidCustomerIds = [];
            foreach ($validated['scoped_customers'] as $customerId) {
                if (!is_numeric($customerId)) {
                    $invalidCustomerIds[] = $customerId;
                    continue;
                }
                
                $exists = \App\Models\Customer::withoutGlobalScopes()
                    ->where('company_id', $authUser->company_id)
                    ->where('customer_id', $customerId)
                    ->exists();
                    
                if (!$exists) {
                    $invalidCustomerIds[] = $customerId;
                }
            }
            
            if (!empty($invalidCustomerIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid customer IDs provided.',
                    'errors' => [
                        'scoped_customers' => [
                            'The following customer IDs do not exist: ' . implode(', ', $invalidCustomerIds)
                        ]
                    ]
                ], 422);
            }
        }

        $validated['name'] = trim(strip_tags($validated['name']));
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['auth_method'] = trim($validated['auth_method'] ?? 'local');
        $validated['status'] = trim($validated['status'] ?? 'active');

        // Get role-based default permissions
        $role = \App\Models\Role::find($validated['role_id']);
        $roleName = $role->name ?? 'Viewer';
        
        // Merge role defaults with any custom permissions provided
        $permissions = \App\Services\RolePermissionService::mergePermissions(
            $roleName,
            $validated['permissions'] ?? null
        );

        $plainPassword = $validated['password'];
        
        $user = User::create([
            'company_id' => $authUser->company_id,
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'auth_method' => $validated['auth_method'],
            'force_password_change' => $validated['force_password_change'] ?? false,
            'permissions' => $permissions,
            'scoped_customers' => $validated['scoped_customers'] ?? null,
        ]);

        // Send welcome email with credentials (only for local auth users)
        if ($validated['auth_method'] === 'local') {
            try {
                Mail::to($user->email)->queue(new WelcomeUserMail($user, $plainPassword));
            } catch (\Exception $e) {
                \Log::warning('Failed to send welcome email to user: ' . $user->email, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ], 201);
    }

    public function updatePermissions(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
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

        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function updateUser(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->user_id . ',user_id',
            'role_id' => 'sometimes|exists:roles,id',
            'scoped_customers' => 'nullable|array',
        ]);
        
        // Validate scoped_customers separately to provide better error messages
        if (isset($validated['scoped_customers']) && is_array($validated['scoped_customers'])) {
            $invalidCustomerIds = [];
            foreach ($validated['scoped_customers'] as $customerId) {
                if (!is_numeric($customerId)) {
                    $invalidCustomerIds[] = $customerId;
                    continue;
                }
                
                $exists = \App\Models\Customer::withoutGlobalScopes()
                    ->where('company_id', $authUser->company_id)
                    ->where('customer_id', $customerId)
                    ->exists();
                    
                if (!$exists) {
                    $invalidCustomerIds[] = $customerId;
                }
            }
            
            if (!empty($invalidCustomerIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid customer IDs provided.',
                    'errors' => [
                        'scoped_customers' => [
                            'The following customer IDs do not exist: ' . implode(', ', $invalidCustomerIds)
                        ]
                    ]
                ], 422);
            }
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'scoped_customers' => $user->scoped_customers,
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

        if (array_key_exists('scoped_customers', $validated)) {
            $user->scoped_customers = $validated['scoped_customers'];
        }

        $user->save();

        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function deactivateUser(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        if ($user->user_id === $authUser->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot deactivate your own account.'
            ], 400);
        }

        $user->status = 'inactive';
        $user->save();

        $user->tokens()->delete();

        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'User deactivated successfully.',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function reactivateUser(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        $user->status = 'active';
        $user->save();

        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'User reactivated successfully.',
            'data' => $sanitisedUser->load('role')
        ]);
    }

    public function deleteUser(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        if ($user->user_id === $authUser->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account.'
            ], 400);
        }

        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.'
        ]);
    }

    public function resetUserPassword(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }
        
        if ($user->auth_method !== 'local') {
            return response()->json([
                'status' => 'error',
                'message' => 'Password reset is only available for local authentication users.'
            ], 400);
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', new StrongPassword()],
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
            'message' => 'User password reset successfully.'
        ]);
    }

    /**
     * Get all available roles
     */
    public function getRoles(Request $request): \Illuminate\Http\JsonResponse
    {
        $roles = Role::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Upload profile picture for a user
     */
    public function uploadProfilePicture(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Delete old profile picture if exists
        if ($user->profile_picture) {
            $oldPath = storage_path('app/public/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Store new profile picture
        $file = $request->file('profile_picture');
        $filename = 'profile_' . $user->user_id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('profile-pictures', $filename, 'public');

        $user->profile_picture = $path;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile picture uploaded successfully.',
            'data' => [
                'profile_picture' => $path,
                'profile_picture_url' => asset('storage/' . $path),
            ]
        ]);
    }

    /**
     * Delete profile picture for a user
     */
    public function deleteProfilePicture(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        if ($user->profile_picture) {
            $oldPath = storage_path('app/public/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            $user->profile_picture = null;
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile picture deleted successfully.'
        ]);
    }

    /**
     * Clear force password change flag after user changes password
     */
    public function clearForcePasswordChange(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', new StrongPassword()],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect.'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->force_password_change = false;
        $user->save();

        // Revoke all tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'user_password_changed_first_login',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
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
            'message' => 'Password changed successfully.',
            'data' => [
                'token' => $token,
            ]
        ]);
    }
}
