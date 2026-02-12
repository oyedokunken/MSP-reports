<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Concerns\LogsAudit;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Validation\ValidationException;

final class UpdateUserPermissionsAction
{
    use LogsAudit;

    /**
     * @param array{permissions: array<string, array<string, bool>>} $data
     * @throws ValidationException
     */
    public function execute(User $authUser, int $userId, array $data): User
    {
        $user = User::where('user_id', $userId)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['User not found in your company.'],
            ]);
        }

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $data['permissions'];
        
        // Merge with defaults to ensure structure
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user->permissions = $permissions;
        $user->save();

        $this->audit($authUser->company_id, 'user_permissions_updated', $user, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'permissions' => $permissions,
        ]);

        return $user;
    }
}
