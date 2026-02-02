<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UpdateUserAction
{
    /**
     * @param array{
     *   name?: string,
     *   email?: string,
     *   role_id?: int,
     *   scoped_customers?: array<int>|null
     * } $data
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

        // Validate scoped customers if provided
        if (isset($data['scoped_customers']) && count($data['scoped_customers']) > 0) {
            $this->validateScopedCustomers($authUser, $data['scoped_customers']);
        }

        if (isset($data['name'])) {
            $user->name = trim(strip_tags($data['name']));
        }

        if (isset($data['email'])) {
            $user->email = trim(strtolower($data['email']));
        }

        if (isset($data['role_id'])) {
            $user->role_id = $data['role_id'];
        }

        if (array_key_exists('scoped_customers', $data)) {
            $user->scoped_customers = $data['scoped_customers'];
        }

        $user->save();

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
}
