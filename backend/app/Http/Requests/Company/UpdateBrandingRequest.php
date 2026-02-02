<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'sometimes|string|max:255',
            'primary_color' => 'sometimes|string|regex:/^#[0-9a-f]{6}$/i',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9a-f]{6}$/i',
            'logo_url' => 'sometimes|nullable|url|max:500',
            'favicon_url' => 'sometimes|nullable|url|max:500',
            'email_from_address' => 'sometimes|nullable|email|max:255',
            'email_from_name' => 'sometimes|nullable|string|max:255',
            'support_email' => 'sometimes|nullable|email|max:255',
            'support_phone' => 'sometimes|nullable|string|max:50',
            'footer_text' => 'sometimes|nullable|string|max:500',
            'report_header_text' => 'sometimes|nullable|string|max:255',
            'smtp_host' => 'sometimes|nullable|string|max:255',
            'smtp_port' => 'sometimes|nullable|integer',
            'smtp_encryption' => 'sometimes|nullable|string|in:tls,ssl,none',
            'smtp_username' => 'sometimes|nullable|string|max:255',
            'smtp_password' => 'sometimes|nullable|string|max:255',
        ];
    }
}
