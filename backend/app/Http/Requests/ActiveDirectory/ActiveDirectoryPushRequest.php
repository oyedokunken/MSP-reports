<?php

declare(strict_types=1);

namespace App\Http\Requests\ActiveDirectory;

use Illuminate\Foundation\Http\FormRequest;

class ActiveDirectoryPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'syncType' => 'required|in:full,delta',
            'syncTimestamp' => 'required|date',
            'domain' => 'required|string|max:255',
            'data' => 'required|array',
            'data.users' => 'required|array',
            'data.securityGroups' => 'required|array',
            'data.groupMemberships' => 'required|array',
            'statistics' => 'required|array',
            'statistics.userCount' => 'required|integer',
            'statistics.groupCount' => 'required|integer',
            'statistics.membershipCount' => 'required|integer',
        ];
    }
}
