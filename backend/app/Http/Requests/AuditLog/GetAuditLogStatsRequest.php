<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;

class GetAuditLogStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
