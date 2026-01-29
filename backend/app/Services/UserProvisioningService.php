<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserProvisioningService
{
    /**
     * Provision a new user via JIT (Just-In-Time) provisioning
     * 
     * @param int $companyId
     * @param string $email
     * @param string $name
     * @param string $authMethod (saml, m365, ldap)
     * @param array<string, mixed> $additionalData Optional additional user data
     * @return User
     */
    public function provisionUser(int $companyId, string $email, string $name, string $authMethod, array $additionalData = []): User
    {
        // Get the Viewer role (default for JIT provisioned users)
        $viewerRole = Role::where('name', 'Viewer')->first();
        
        if (!$viewerRole) {
            // If Viewer role doesn't exist, create it
            $viewerRole = Role::create([
                'name' => 'Viewer',
                'description' => 'Read-only access to reports and data'
            ]);
        }

        // Get role-based default permissions (Viewer role)
        $permissions = RolePermissionService::mergePermissions(
            'Viewer',
            $additionalData['permissions'] ?? null
        );

        // Create the user
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'role_id' => $viewerRole->id,
            'name' => trim(strip_tags($name)),
            'email' => trim(strtolower($email)),
            'password' => Hash::make(Str::random(32)), // Random password (won't be used)
            'status' => 'active',
            'auth_method' => $authMethod,
            'permissions' => $permissions,
            'scoped_customers' => $additionalData['scoped_customers'] ?? null,
            'email_verified_at' => now(), // Auto-verify for external auth
        ]);

        // Log the JIT provisioning event
        AuditLog::create([
            'company_id' => $companyId,
            'event_type' => 'user_jit_provisioned',
            'actor_type' => 'System',
            'actor_id' => null,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'auth_method' => $authMethod,
                'email' => $email,
                'name' => $name,
                'role' => 'Viewer',
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Find or provision a user based on email and auth method
     * 
     * @param int $companyId
     * @param string $email
     * @param string $name
     * @param string $authMethod
     * @param array<string, mixed> $additionalData
     * @return User
     */
    public function findOrProvisionUser(int $companyId, string $email, string $name, string $authMethod, array $additionalData = []): User
    {
        // Try to find existing user by email and company
        $user = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('email', trim(strtolower($email)))
            ->first();

        if ($user) {
            // Update last login timestamp
            $user->last_login_at = now();
            $user->save();

            // Log the login
            AuditLog::create([
                'company_id' => $companyId,
                'event_type' => 'user_login',
                'actor_type' => 'User',
                'actor_id' => $user->user_id,
                'target_type' => 'User',
                'target_id' => $user->user_id,
                'event_data' => json_encode([
                    'auth_method' => $authMethod,
                    'email' => $email,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        }

        // User doesn't exist, provision new user
        return $this->provisionUser($companyId, $email, $name, $authMethod, $additionalData);
    }

    /**
     * Update user attributes from external auth provider
     * 
     * @param User $user
     * @param array<string, mixed> $attributes
     * @return User
     */
    public function updateUserFromProvider(User $user, array $attributes): User
    {
        $updated = false;

        // Update name if provided and different
        if (isset($attributes['name']) && $user->name !== $attributes['name']) {
            $user->name = trim(strip_tags($attributes['name']));
            $updated = true;
        }

        // Update email if provided and different
        if (isset($attributes['email']) && $user->email !== trim(strtolower($attributes['email']))) {
            $user->email = trim(strtolower($attributes['email']));
            $updated = true;
        }

        // Update scoped_customers if provided
        if (isset($attributes['scoped_customers'])) {
            $user->scoped_customers = $attributes['scoped_customers'];
            $updated = true;
        }

        if ($updated) {
            $user->save();

            // Log the update
            AuditLog::create([
                'company_id' => $user->company_id,
                'event_type' => 'user_updated_from_provider',
                'actor_type' => 'System',
                'actor_id' => null,
                'target_type' => 'User',
                'target_id' => $user->user_id,
                'event_data' => json_encode([
                    'updated_attributes' => array_keys($attributes),
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        return $user;
    }
}
