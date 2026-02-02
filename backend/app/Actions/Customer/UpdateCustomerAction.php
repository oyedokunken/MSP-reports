<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerAction
{
    /**
     * @param array{
     *   canonical_name?: string,
     *   status?: string,
     *   report_destination?: string|null,
     *   metadata?: array<string, mixed>
     * } $data
     * @throws ValidationException
     */
    public function execute(Company $company, int $customerId, array $data): Customer
    {
        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer' => ['Customer not found in your company.'],
            ]);
        }

        if (isset($data['canonical_name'])) {
            $customer->canonical_name = trim(strip_tags($data['canonical_name']));
        }

        if (array_key_exists('status', $data)) {
            $customer->status = $data['status'];
        }

        if (array_key_exists('report_destination', $data)) {
            $customer->report_destination = $data['report_destination'];
        }

        if (array_key_exists('metadata', $data)) {
            $customer->metadata = array_merge($customer->metadata ?? [], $data['metadata']);
        }

        $customer->save();

        return $customer;
    }
}
