<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SplitCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_customer_name' => 'required|string|max:255',
            'integration_customer_ids' => 'required|array|min:1',
            'integration_customer_ids.*' => 'integer|exists:integration_customers,integration_customer_id',
        ];
    }
}
