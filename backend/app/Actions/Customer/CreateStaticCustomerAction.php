<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class CreateStaticCustomerAction
{
    /**
     * @param array{
     *   canonical_name: string,
     *   status?: string,
     *   report_destination?: string|null,
     *   metadata?: array<string, mixed>
     * } $data
     */
    public function execute(Company $company, array $data): Customer
    {
        return DB::transaction(function () use ($company, $data) {
            return Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => $data['canonical_name'],
                'status' => $data['status'] ?? 'active',
                'is_static' => true,
                'report_destination' => $data['report_destination'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
        });
    }
}
