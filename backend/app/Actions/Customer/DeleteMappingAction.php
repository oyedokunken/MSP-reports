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

final class DeleteMappingAction
{
    public function execute(
        User $authUser,
        Company $company,
        int $customerId,
        int $mappingId,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found or not in your company.'
            ]);
        }

        $mapping = CustomerMapping::where('customer_mapping_id', $mappingId)
            ->where('customer_id', $customer->customer_id)
            ->first();

        if (!$mapping) {
            throw ValidationException::withMessages([
                'mapping_id' => 'Mapping not found for this customer.'
            ]);
        }

        DB::transaction(function () use ($authUser, $company, $customer, $mapping, $ipAddress, $userAgent) {
            // Update integration_customers to set customer_id to null
            IntegrationCustomer::where('integration_customer_id', $mapping->integration_customer_id)
                ->update(['customer_id' => null]);

            $integrationCustomerId = $mapping->integration_customer_id;
            
            $mapping->delete();

            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'customer_mapping_deleted',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $customer->customer_id,
                'event_data' => json_encode([
                    'customer_id' => $customer->customer_id,
                    'customer_name' => $customer->canonical_name,
                    'integration_customer_id' => $integrationCustomerId,
                    'mapping_id' => $mapping->customer_mapping_id,
                ]),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        });
    }
}
