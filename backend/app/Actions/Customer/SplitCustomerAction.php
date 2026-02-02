<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SplitCustomerAction
{
    /**
     * @param array<int> $integrationCustomerIds
     * @return array{original_customer: Customer, new_customer: Customer}
     */
    public function execute(
        User $authUser,
        Company $company,
        int $originalCustomerId,
        string $newCustomerName,
        array $integrationCustomerIds,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $originalCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $originalCustomerId)
            ->first();

        if (!$originalCustomer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found in your company.'
            ]);
        }

        return DB::transaction(function () use (
            $authUser,
            $company,
            $originalCustomer,
            $newCustomerName,
            $integrationCustomerIds,
            $ipAddress,
            $userAgent
        ) {
            $newCustomer = Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => trim(strip_tags($newCustomerName)),
                'status' => 'active',
                'report_destination' => $originalCustomer->report_destination,
                'metadata' => $originalCustomer->metadata,
            ]);

            $movedCount = CustomerMapping::whereIn('integration_customer_id', $integrationCustomerIds)
                ->where('customer_id', $originalCustomer->customer_id)
                ->update(['customer_id' => $newCustomer->customer_id]);

            if ($movedCount === 0) {
                throw ValidationException::withMessages([
                    'integration_customer_ids' => 'No integration customers were moved. Check that they belong to the original customer.'
                ]);
            }
            
            // Also update the customer_id on integration_customers table
            IntegrationCustomer::whereIn('integration_customer_id', $integrationCustomerIds)
                ->update(['customer_id' => $newCustomer->customer_id]);

            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'customer_split',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $originalCustomer->customer_id,
                'event_data' => json_encode([
                    'original_customer' => [
                        'id' => $originalCustomer->customer_id,
                        'name' => $originalCustomer->canonical_name,
                    ],
                    'new_customer' => [
                        'id' => $newCustomer->customer_id,
                        'name' => $newCustomer->canonical_name,
                    ],
                    'integration_customers_moved' => $movedCount,
                ]),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return [
                'original_customer' => $originalCustomer->load('integrationCustomers'),
                'new_customer' => $newCustomer->load('integrationCustomers'),
            ];
        });
    }
}
