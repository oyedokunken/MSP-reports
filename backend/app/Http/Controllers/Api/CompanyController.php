<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Company\UpdateBrandingAction;
use App\Actions\Company\UpdateSettingsAction;
use App\Actions\Company\UploadLogoAction;
use App\Http\Requests\Company\UpdateBrandingRequest;
use App\Http\Requests\Company\UpdateSettingsRequest;
use App\Http\Requests\Company\UploadLogoRequest;
use App\Http\Controllers\Controller;
use App\Services\ReportNamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CompanyController extends Controller
{
    /**
     * Get company settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            // Get logo URLs from settings with full URLs
            $settings = $company->settings ?? [];
            if (isset($settings['white_label'])) {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
                $publicDisk = Storage::disk('public');

                if (!empty($settings['white_label']['logo_url'])) {
                    $settings['white_label']['logo_url'] = $publicDisk->url($settings['white_label']['logo_url']);
                }
                if (!empty($settings['white_label']['favicon_url'])) {
                    $settings['white_label']['favicon_url'] = $publicDisk->url($settings['white_label']['favicon_url']);
                }
            }

            $logoUrl = $settings['white_label']['logo_url'] ?? null;
            $faviconUrl = $settings['white_label']['favicon_url'] ?? null;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'company_id' => $company->company_id,
                    'name' => $company->name,
                    'abn' => $company->abn,
                    'contact_email' => $company->contact_email,
                    'contact_phone' => $company->contact_phone,
                    'address' => $company->address,
                    'logo_url' => $logoUrl,
                    'profile_logo_url' => $faviconUrl,
                    'status' => $company->status,
                    'subscription_tier' => $company->subscription_tier,
                    'settings' => $settings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get company settings', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve company settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update company settings
     */
    public function updateSettings(UpdateSettingsRequest $request, UpdateSettingsAction $action): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $company = $action->execute($company, $request->validated(), $authUser->user_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Company settings updated successfully',
                'data' => [
                    'company_id' => $company->company_id,
                    'name' => $company->name,
                    'abn' => $company->abn,
                    'contact_email' => $company->contact_email,
                    'contact_phone' => $company->contact_phone,
                    'address' => $company->address,
                    'status' => $company->status,
                    'subscription_tier' => $company->subscription_tier,
                    'settings' => $company->settings ?? [],
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update company settings', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update company settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update company branding (for white-label settings)
     */
    public function updateBranding(UpdateBrandingRequest $request, UpdateBrandingAction $action): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $whiteLabelSettings = $action->execute($company, $request->validated(), $authUser->user_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Company branding updated successfully',
                'data' => $whiteLabelSettings
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update company branding', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update company branding: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload company logo
     */
    public function uploadLogo(UploadLogoRequest $request, UploadLogoAction $action): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $type = $request->input('type');
            $file = $request->file('logo');

            $result = $action->execute($company, $file, (string) $type, $authUser->user_id);

            return response()->json([
                'status' => 'success',
                'message' => ucfirst((string) $type) . ' logo uploaded successfully',
                'data' => $result
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to upload company logo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete company logo
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $request->validate([
                'type' => 'required|in:company,profile',
            ]);

            $type = $request->input('type');

            // Get current settings
            $settings = $company->settings ?? [];
            if (!isset($settings['white_label'])) {
                $settings['white_label'] = [];
            }

            // Get logo path from settings
            $logoPath = $type === 'company'
                ? ($settings['white_label']['logo_url'] ?? null)
                : ($settings['white_label']['favicon_url'] ?? null);

            // Delete file if exists
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            // Update settings
            if ($type === 'company') {
                unset($settings['white_label']['logo_url']);
            } else {
                unset($settings['white_label']['favicon_url']);
            }

            $company->settings = $settings;
            $company->save();

            // Log the deletion
            Log::info('Company logo deleted', [
                'company_id' => $company->company_id,
                'type' => $type,
                'user_id' => $authUser->user_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($type) . ' logo deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete company logo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get report naming settings
     */
    public function getReportNaming(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $namingService = app(ReportNamingService::class);
            $settings = $company->settings ?? [];
            $pattern = $settings['report_settings']['naming_pattern'] ?? ReportNamingService::DEFAULT_PATTERN;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'naming_pattern' => $pattern,
                    'available_tokens' => $namingService->getAvailableTokens(),
                    'preview' => $namingService->generatePreview($pattern),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get report naming settings', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve report naming settings',
            ], 500);
        }
    }

    /**
     * Update report naming settings
     */
    public function updateReportNaming(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            $validated = $request->validate([
                'naming_pattern' => 'required|string|max:500',
            ]);

            $namingService = app(ReportNamingService::class);
            $pattern = $validated['naming_pattern'];

            // Validate pattern has at least one token
            if (!$namingService->validatePattern($pattern)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pattern must contain at least one valid token like {customer_name} or {date}',
                ], 422);
            }

            // Update settings
            $settings = $company->settings ?? [];
            if (!isset($settings['report_settings'])) {
                $settings['report_settings'] = [];
            }
            $settings['report_settings']['naming_pattern'] = $pattern;

            $company->settings = $settings;
            $company->save();

            Log::info('Report naming settings updated', [
                'company_id' => $company->company_id,
                'pattern' => $pattern,
                'user_id' => $authUser->user_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Report naming settings updated successfully',
                'data' => [
                    'naming_pattern' => $pattern,
                    'preview' => $namingService->generatePreview($pattern),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update report naming settings', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update report naming settings',
            ], 500);
        }
    }
}
