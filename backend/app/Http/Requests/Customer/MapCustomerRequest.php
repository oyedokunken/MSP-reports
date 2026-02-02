<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class MapCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'integration_customer_id' => 'required|integer|exists:integration_customers,integration_customer_id',
            'create_new' => 'sometimes|boolean',
        ];

        if (!$this->boolean('create_new')) {
            $rules['customer_id'] = 'required|integer|exists:customers,customer_id';
        } else {
            $rules['canonical_name'] = 'required|string|max:255';
            $rules['status'] = 'sometimes|in:active,inactive';
            $rules['report_destination'] = 'sometimes|email|nullable';
            $rules['metadata'] = 'sometimes|array';
        }

        return $rules;
    }
}
