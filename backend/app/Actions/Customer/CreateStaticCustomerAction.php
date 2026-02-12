<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\InAppNotificationService;
use Illuminate\Support\Facades\DB;

final class CreateStaticCustomerAction
{
    use LogsAudit;

    public function __construct(
        private readonly InAppNotificationService $notificationService,
    ) {}

    /**
     * @param array{
     *   canonical_name: string,
     *   status?: string,
     *   report_destination?: string|null,
     *   metadata?: array<string, mixed>
     * } $data
     */
    public function execute(Company $company, array $data, ?User $actor = null): Customer
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $customer = Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => $data['canonical_name'],
                'status' => $data['status'] ?? 'active',
                'is_static' => true,
                'report_destination' => $data['report_destination'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($actor) {
                $this->notificationService->customerCreated($customer, $actor);
            }

            $this->audit($company->company_id, 'customer_created', $customer, [
                'customer_name' => $customer->canonical_name,
            ]);

            return $customer;
        });
    }
}
