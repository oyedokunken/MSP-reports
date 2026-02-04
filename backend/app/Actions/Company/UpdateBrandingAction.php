<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Support\Facades\Log;

final class UpdateBrandingAction
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $data, int $userId): array
    {
        $settings = $company->settings ?? [];

        if (!isset($settings['white_label'])) {
            $settings['white_label'] = [];
        }
        if (!isset($settings['mail_settings'])) {
            $settings['mail_settings'] = [];
        }
        if (!isset($settings['mail_settings']['smtp'])) {
            $settings['mail_settings']['smtp'] = [];
        }

        // Map branding fields
        $whiteLabelFields = [
            'company_name', 'primary_color', 'secondary_color', 'logo_url',
            'favicon_url', 'email_from_address', 'email_from_name',
            'support_email', 'support_phone', 'footer_text', 'report_header_text'
        ];

        foreach ($whiteLabelFields as $field) {
            if (array_key_exists($field, $data)) {
                $settings['white_label'][$field] = $data[$field];
            }
        }

        // Map SMTP fields
        $smtpFieldMap = [
            'smtp_host' => 'host',
            'smtp_port' => 'port',
            'smtp_encryption' => 'encryption',
            'smtp_username' => 'username',
        ];

        foreach ($smtpFieldMap as $inputField => $settingField) {
            if (array_key_exists($inputField, $data)) {
                $settings['mail_settings']['smtp'][$settingField] = $data[$inputField];
            }
        }

        // Only update password if provided and not empty
        if (!empty($data['smtp_password'])) {
            $settings['mail_settings']['smtp']['password'] = $data['smtp_password'];
        }

        $company->settings = $settings;
        $company->save();

        Log::info('Company branding updated', [
            'company_id' => $company->company_id,
            'user_id' => $userId,
        ]);

        $whiteLabel = $settings['white_label'] ?? [];
        if (!empty($whiteLabel['logo_url'])) {
            $whiteLabel['logo_url'] = \Illuminate\Support\Facades\Storage::disk('public')->url($whiteLabel['logo_url']);
        }
        if (!empty($whiteLabel['favicon_url'])) {
            $whiteLabel['favicon_url'] = \Illuminate\Support\Facades\Storage::disk('public')->url($whiteLabel['favicon_url']);
        }

        return $whiteLabel;
    }
}
