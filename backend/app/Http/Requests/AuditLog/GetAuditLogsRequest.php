<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;

class GetAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'event_type' => 'nullable|string|max:255',
            'actor_type' => 'nullable|string|max:255',
            'target_type' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }
}
