<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerReportsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $customerId = $this->route('id');

        return \App\Models\Customer::where('company_id', $user->company_id)
            ->where('customer_id', $customerId)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedules' => 'required|array|min:1',
            'schedules.*.report_type' => 'required|string',
            'schedules.*.frequency' => 'required|string|in:daily,weekly,monthly,yearly,on-demand',
            'schedules.*.time_of_day' => 'nullable|string',
            'schedules.*.active' => 'nullable|boolean',
            'schedules.*.schedule_config' => 'nullable|array',
        ];
    }
}
