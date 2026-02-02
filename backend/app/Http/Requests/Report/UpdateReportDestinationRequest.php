<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $customerId = $this->route('id');

        return \App\Models\Customer::where('company_id', $user->company_id)
            ->where('customer_id', $customerId)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'method' => 'required|string|in:email,api,download',
            'destination' => 'required|string|email',
            'cc' => 'nullable|array',
            'cc.*' => 'nullable|email',
        ];
    }
}
