<?php

declare(strict_types=1);

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

class ExchangePushRequest extends FormRequest
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
            'syncType' => 'required|in:full,delta',
            'syncTimestamp' => 'required|date',
            'exchangeOrganization' => 'nullable|string',
            'exchangeVersion' => 'nullable|string',
            'data' => 'required|array',
            'data.mailboxes' => 'required|array',
            'data.emailAliases' => 'nullable|array',
            'data.permissions' => 'nullable|array',
            'data.statistics' => 'nullable|array',
            'statistics' => 'nullable|array',
        ];
    }
}
