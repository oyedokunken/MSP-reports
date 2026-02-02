<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class TestDeliveryConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => 'required|string',
            'config' => 'required|array',
        ];
    }
}
