<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateStaticCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canonical_name' => 'required|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'report_destination' => 'sometimes|email|nullable',
            'metadata' => 'sometimes|array',
        ];
    }
}
