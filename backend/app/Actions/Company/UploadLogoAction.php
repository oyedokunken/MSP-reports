<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UploadLogoAction
{
    /**
     * @throws ValidationException
     * @return array{logo_path: string, logo_url: string, settings_key: string}
     */
    public function execute(Company $company, UploadedFile $file, string $type, int $userId): array
    {
        // Validate dimensions
        $image = getimagesize($file->getRealPath());
        if ($image) {
            $width = $image[0];
            $height = $image[1];

            if ($type === 'company' && ($width > 800 || $height > 800)) {
                throw ValidationException::withMessages([
                    'logo' => ['Company logo dimensions should not exceed 800x800 pixels'],
                ]);
            }

            if ($type === 'profile' && ($width > 400 || $height > 400)) {
                throw ValidationException::withMessages([
                    'logo' => ['Profile logo dimensions should not exceed 400x400 pixels'],
                ]);
            }
        }

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

        return [
            'logo_path' => $path,
            'logo_url' => Storage::disk('public')->url($path),
            'settings_key' => $type === 'company' ? 'white_label.logo_url' : 'white_label.favicon_url',
        ];
    }
}
