<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\LicenseChecker;
use App\Models\License;

class LicenseController extends Controller
{
    public function index(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $licenses = $licenseChecker->getActiveLicenses($company);
        $expiringLicenses = $licenseChecker->getExpiringLicenses($company, 30);
        $userLicenseStatus = $licenseChecker->getUserLicenseStatus($company);

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_licenses' => $licenses,
                'expiring_soon' => $expiringLicenses,
                'user_license_status' => $userLicenseStatus,
                'license_enforcement_enabled' => $company->settings['licensing']['license_enforcement'] ?? false,
            ]
        ]);
    }

    public function checkIntegrationLicense(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validate([
            'integration_type' => 'required|string',
        ]);

        $check = $licenseChecker->canActivateIntegration($company, $validated['integration_type']);

        return response()->json([
            'status' => 'success',
            'data' => $check
        ]);
    }

    public function checkRetentionLicense(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validate([
            'requested_months' => 'required|integer|min:1',
        ]);

        $check = $licenseChecker->canExtendRetention($company, $validated['requested_months']);

        return response()->json([
            'status' => 'success',
            'data' => $check
        ]);
    }

    public function checkUserLicense(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $check = $licenseChecker->canCreateUser($company);
        $status = $licenseChecker->getUserLicenseStatus($company);

        return response()->json([
            'status' => 'success',
            'data' => [
                'can_create' => $check,
                'license_status' => $status,
            ]
        ]);
    }

    public function getLicenseUsage(Request $request, LicenseChecker $licenseChecker): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Get integration usage
        $activeIntegrations = $company->integrations()
            ->where('status', 'active')
            ->orWhere('status', 'importing_historical')
            ->count();

        $integrationLicenses = $company->licenses()
            ->where('license_type', 'integration')
            ->where('status', 'active')
            ->count();

        // Get user usage
        $userStatus = $licenseChecker->getUserLicenseStatus($company);

        // Get retention info
        $retentionLicense = $company->licenses()
            ->where('license_type', 'retention')
            ->where('status', 'active')
            ->first();

        $maxRetentionMonths = $retentionLicense 
            ? ($retentionLicense->metadata['max_retention_months'] ?? 'unlimited')
            : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'integrations' => [
                    'active' => $activeIntegrations,
                    'licensed' => $integrationLicenses,
                ],
                'users' => $userStatus,
                'retention' => [
                    'max_months' => $maxRetentionMonths,
                    'licensed' => $retentionLicense !== null,
                ],
                'license_enforcement_enabled' => $company->settings['licensing']['license_enforcement'] ?? false,
            ]
        ]);
    }
}
