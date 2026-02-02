<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryConfigRequest extends FormRequest
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
            'enabled_methods' => 'required|array|min:1',
            'enabled_methods.*' => 'string',
            'email' => 'nullable|array',
            'email.destination' => 'nullable|email',
            'email.cc' => 'nullable|array',
            'email.cc.*' => 'nullable|email',
            'sharepoint' => 'nullable|array',
            'sharepoint.tenant_id' => 'nullable|string',
            'sharepoint.client_id' => 'nullable|string',
            'sharepoint.client_secret' => 'nullable|string',
            'sharepoint.site_id' => 'nullable|string',
            'sharepoint.drive_id' => 'nullable|string',
            'sharepoint.folder_path' => 'nullable|string',
        ];
    }
}
