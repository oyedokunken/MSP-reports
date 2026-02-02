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

final class MapCustomerAction
{
    /**
     * @param array{
     *   integration_customer_id: int,
     *   customer_id?: int,
     *   create_new?: bool,
     *   canonical_name?: string,
     *   status?: string,
     *   report_destination?: string|null,
     *   metadata?: array<string, mixed>
     * } $data
     * @throws ValidationException
     * @return array{customer: Customer, message: string, created_new: bool}
     */
    public function execute(User $authUser, Company $company, array $data, ?string $ipAddress): array
    {
        /** @var IntegrationCustomer|null $integrationCustomer */
        $integrationCustomer = IntegrationCustomer::with('integration')
            ->find($data['integration_customer_id']);

        if (!$integrationCustomer) {
            throw ValidationException::withMessages([
                'integration_customer_id' => ['Integration customer not found.'],
            ]);
        }

        // Verify tenant isolation
        if ($integrationCustomer->integration->company_id !== $company->company_id) {
            throw ValidationException::withMessages([
                'integration_customer_id' => ['Integration customer does not belong to your company.'],
            ]);
        }

        // Check if already mapped
        if ($integrationCustomer->customer_id) {
            throw ValidationException::withMessages([
                'integration_customer_id' => ['Integration customer is already mapped.'],
            ]);
        }

        return DB::transaction(function () use ($authUser, $company, $data, $integrationCustomer, $ipAddress) {
            $createNew = $data['create_new'] ?? false;

            if ($createNew) {
                if (!isset($data['canonical_name'])) {
                    throw ValidationException::withMessages([
                        'canonical_name' => ['Canonical name is required when creating a new customer.'],
                    ]);
                }
                
                $customer = Customer::create([
                    'company_id' => $company->company_id,
                    'canonical_name' => $data['canonical_name'],
                    'status' => $data['status'] ?? 'active',
                    'is_static' => false,
                    'report_destination' => $data['report_destination'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ]);
                $message = 'New customer created and integration customer mapped successfully.';
            } else {
                if (!isset($data['customer_id'])) {
                    throw ValidationException::withMessages([
                        'customer_id' => ['Customer ID is required when mapping to existing customer.'],
                    ]);
                }
                
                $customer = Customer::where('company_id', $company->company_id)
                    ->where('customer_id', $data['customer_id'])
                    ->first();

                if (!$customer) {
                    throw ValidationException::withMessages([
                        'customer_id' => ['Customer not found in your company.'],
                    ]);
                }
                $message = 'Integration customer mapped successfully.';
            }

            // Map the integration customer
            $integrationCustomer->customer_id = $customer->customer_id;
            $integrationCustomer->save();

            // Create mapping record
            CustomerMapping::create([
                'customer_id' => $customer->customer_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'mapping_type' => 'manual',
                'confidence_score' => 100,
                'mapped_by_user_id' => $authUser->user_id,
                'mapped_at' => now(),
                'notes' => $createNew ? 'New customer created via manual mapping' : 'Manually mapped to existing customer',
            ]);

            // Audit log
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => $createNew ? 'customer_created_and_mapped' : 'customer_mapped',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $customer->customer_id,
                'event_data' => [
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'integration_type' => $integrationCustomer->integration->integration_type,
                    'external_id' => $integrationCustomer->external_id,
                    'canonical_name' => $customer->canonical_name,
                    'created_new' => $createNew,
                ],
                'ip_address' => $ipAddress,
            ]);

            return [
                'customer' => $customer,
                'message' => $message,
                'created_new' => $createNew,
            ];
        });
    }
}
