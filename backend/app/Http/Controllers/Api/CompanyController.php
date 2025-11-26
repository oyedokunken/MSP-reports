<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    /**
     * Get company settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            return response()->json([
                'status' => 'success',
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
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            // Validate input
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'abn' => 'sometimes|nullable|string|max:50',
                'contact_email' => 'sometimes|nullable|email|max:255',
                'contact_phone' => 'sometimes|nullable|string|max:50',
                'address' => 'sometimes|nullable|string|max:1000',
                'settings' => 'sometimes|nullable|array',
            ]);

            // Update company
            $company->update($validated);

            // Log the update
            Log::info('Company settings updated', [
                'company_id' => $company->company_id,
                'updated_fields' => array_keys($validated),
                'user_id' => $authUser->user_id,
            ]);

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

        } catch (\Illuminate\Validation\ValidationException $e) {
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
    public function updateBranding(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            $company = $authUser->company;

            // Validate input
            $validated = $request->validate([
                'company_name' => 'sometimes|string|max:255',
                'primary_color' => 'sometimes|string|regex:/^#[0-9a-f]{6}$/i',
                'email_from_address' => 'sometimes|nullable|email|max:255',
                'email_from_name' => 'sometimes|nullable|string|max:255',
            ]);

            // Get current settings or initialize empty array
            $settings = $company->settings ?? [];
            
            // Update white_label section
            if (!isset($settings['white_label'])) {
                $settings['white_label'] = [];
            }

            // Map frontend field names to backend structure
            if (isset($validated['company_name'])) {
                $settings['white_label']['company_name'] = $validated['company_name'];
            }
            if (isset($validated['primary_color'])) {
                $settings['white_label']['primary_color'] = $validated['primary_color'];
            }
            if (isset($validated['email_from_address'])) {
                $settings['white_label']['email_from_address'] = $validated['email_from_address'];
            }
            if (isset($validated['email_from_name'])) {
                $settings['white_label']['email_from_name'] = $validated['email_from_name'];
            }

            // Update company settings
            $company->settings = $settings;
            $company->save();

            // Log the update
            Log::info('Company branding updated', [
                'company_id' => $company->company_id,
                'user_id' => $authUser->user_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Company branding updated successfully',
                'data' => $settings['white_label'] ?? []
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
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
}
