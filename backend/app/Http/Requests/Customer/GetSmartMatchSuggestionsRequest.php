<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetSmartMatchSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'integration_customer_ids' => 'required|array|min:1|max:50',
            'integration_customer_ids.*' => 'integer',
            'use_agent' => 'sometimes|boolean',
        ];
    }
}
