<?php

declare(strict_types=1);

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class CheckRetentionLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_months' => 'required|integer|min:1',
        ];
    }
}
