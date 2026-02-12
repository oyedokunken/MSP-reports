<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Concerns\LogsAudit;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UploadLogoAction
{
    use LogsAudit;

    /**
     * @throws ValidationException
     * @return array{logo_path: string, logo_url: string, settings_key: string}
     */
    public function execute(Company $company, UploadedFile $file, string $type, int $userId): array
    {
        $settings = $company->settings ?? [];
        if (!isset($settings['white_label'])) {
            $settings['white_label'] = [];
        }

        // Delete old logo
        $oldLogoPath = $type === 'company'
            ? ($settings['white_label']['logo_url'] ?? null)
            : ($settings['white_label']['favicon_url'] ?? null);

        if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        // Store new logo
        $extension = $file->getClientOriginalExtension();
        $filename = 'company_' . $company->company_id . '_' . $type . '_' . Str::random(10) . '.' . $extension;
        $path = $file->storeAs('logos', $filename, 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'logo' => ['Failed to store logo.'],
            ]);
        }

        // Update settings
        if ($type === 'company') {
            $settings['white_label']['logo_url'] = $path;
        } else {
            $settings['white_label']['favicon_url'] = $path;
        }

        $company->settings = $settings;
        $company->save();

        Log::info('Company logo uploaded', [
            'company_id' => $company->company_id,
            'type' => $type,
            'filename' => $filename,
            'user_id' => $userId,
        ]);

        $this->audit($company->company_id, 'logo_uploaded', $company, [
            'type' => $type,
            'filename' => $filename,
        ]);

        return [
            'logo_path' => $path,
            'logo_url' => '/storage/' . $path,
            'settings_key' => $type === 'company' ? 'white_label.logo_url' : 'white_label.favicon_url',
        ];
    }
}
