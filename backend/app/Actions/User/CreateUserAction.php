<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Mail\WelcomeUserMail;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseChecker;
use App\Services\RolePermissionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class CreateUserAction
{
    public function __construct(
        private readonly LicenseChecker $licenseChecker,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   role_id: int,
     *   status?: string,
     *   auth_method?: string,
     *   permissions?: array<string, mixed>|null,
     *   scoped_customers?: array<int>|null,
     *   force_password_change?: bool
     * } $data
     * @throws ValidationException
     */
    public function execute(User $authUser, array $data): User
    {
        // Check license
        $licenseCheck = $this->licenseChecker->canCreateUser($authUser->company);
        if (!$licenseCheck['allowed']) {
            throw ValidationException::withMessages([
                'license' => [$licenseCheck['reason']],
            ]);
        }

        // Validate scoped customers if provided
        $scopedCustomers = $data['scoped_customers'] ?? null;
        if ($scopedCustomers !== null && count($scopedCustomers) > 0) {
            $this->validateScopedCustomers($authUser, $scopedCustomers);
        }

        // Get role-based permissions
        $role = Role::find($data['role_id']);
        $roleName = $role->name ?? 'Viewer';
        $permissions = RolePermissionService::mergePermissions(
            $roleName,
            $data['permissions'] ?? null
        );

        $plainPassword = $data['password'];

        $user = User::create([
            'company_id' => $authUser->company_id,
            'role_id' => $data['role_id'],
            'name' => trim(strip_tags($data['name'])),
            'email' => trim(strtolower($data['email'])),
            'password' => Hash::make($data['password']),
            'status' => trim($data['status'] ?? 'active'),
            'auth_method' => trim($data['auth_method'] ?? 'local'),
            'force_password_change' => $data['force_password_change'] ?? false,
            'permissions' => $permissions,
            'scoped_customers' => $data['scoped_customers'] ?? null,
        ]);

        // Send welcome email for local auth users
        $this->sendWelcomeEmail($user, $plainPassword, $data['auth_method'] ?? 'local');

        return $user;
    }

    /**
     * @param array<int|string> $scopedCustomers
     * @throws ValidationException
     */
    private function validateScopedCustomers(User $authUser, array $scopedCustomers): void
    {
        $invalidIds = [];
        foreach ($scopedCustomers as $customerId) {
            if (!is_int($customerId) && !is_numeric($customerId)) {
                $invalidIds[] = $customerId;
                continue;
            }

            $exists = Customer::withoutGlobalScopes()
                ->where('company_id', $authUser->company_id)
                ->where('customer_id', $customerId)
                ->exists();

            if (!$exists) {
                $invalidIds[] = $customerId;
            }
        }

        if (!empty($invalidIds)) {
            throw ValidationException::withMessages([
                'scoped_customers' => ['The following customer IDs do not exist: ' . implode(', ', array_map('strval', $invalidIds))],
            ]);
        }
    }

    private function sendWelcomeEmail(User $user, string $plainPassword, string $authMethod): void
    {
        if ($authMethod !== 'local') {
            return;
        }

        try {
            Mail::to($user->email)->queue(new WelcomeUserMail($user, $plainPassword));
        } catch (\Exception $e) {
            Log::warning('Failed to send welcome email to user: ' . $user->email, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
