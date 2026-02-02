<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class GetCustomerReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $customerId = $this->route('id');

        return Customer::where('company_id', $user->company_id)
            ->where('customer_id', $customerId)
            ->exists();
    }

    public function rules(): array
    {
        return [];
    }
}
