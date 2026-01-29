<?php

declare(strict_types=1);

namespace App\Integrations\M365;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

final class M365Client
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected ?string $accessToken = null;

    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
    }

    /**
     * Get OAuth access token for Microsoft Graph API
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $tenantId = $this->credentials['tenant_id'] ?? null;
        $clientId = $this->credentials['client_id'] ?? null;
        $clientSecret = $this->credentials['client_secret'] ?? null;

        if (!$tenantId || !$clientId || !$clientSecret) {
            throw new \Exception("M365 Integration requires tenant_id, client_id, and client_secret");
        }

        $cacheKey = "m365_token_" . md5($clientId . $tenantId);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            $this->accessToken = $cached;
            return $cached;
        }

        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = \Illuminate\Support\Facades\Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            $error = $response->json('error_description') ?? $response->body();
            throw new \Exception("Failed to get M365 access token: {$error}");
        }

        $this->accessToken = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 3600) - 300; // 5 min buffer

        \Illuminate\Support\Facades\Cache::put($cacheKey, $this->accessToken, $expiresIn);

        return $this->accessToken;
    }

    public function testConnection(): bool
    {
        try {
            $token = $this->getAccessToken();
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->get("{$this->baseUrl}/organization");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('M365Client::testConnection failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get user licensing information including direct and group-based assignments
     * 
     * @return array<string, mixed>
     */
    public function getUserLicensing(string $upn): array
    {
        $token = $this->getAccessToken();
        
        // Fetch user object with assignedLicenses and licenseAssignmentStates
        // @see https://learn.microsoft.com/en-us/graph/api/user-get?view=graph-rest-1.0
        $userResponse = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("{$this->baseUrl}/users/{$upn}", [
                '$select' => 'userPrincipalName,displayName,assignedLicenses,licenseAssignmentStates'
            ]);

        if (!$userResponse->successful()) {
            $error = $userResponse->json('error.message') ?? 'User not found';
            throw new \Exception("Failed to fetch M365 user licensing for {$upn}: {$error}");
        }

        $userData = $userResponse->json();
        $assignments = $userData['assignedLicenses'] ?? [];
        $assignmentStates = $userData['licenseAssignmentStates'] ?? [];

        // Fetch Subscribed SKUs to get Part Numbers
        $skusResponse = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("{$this->baseUrl}/subscribedSkus");
        
        /** @var array<int, array<string, mixed>> $skus */
        $skus = $skusResponse->successful() ? $skusResponse->json('value') : [];
        $skuPartNumbers = collect($skus)->pluck('skuPartNumber', 'skuId')->toArray();

        // Process licenses
        $licenses = [];
        foreach ($assignments as $assignment) {
            $skuId = $assignment['skuId'];
            /** @var array<int, array<string, mixed>> $assignmentStates */
            /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $statesCollection */
            $statesCollection = collect($assignmentStates);
            $state = $statesCollection->firstWhere('skuId', $skuId);
            
            $source = 'Direct';
            $groupId = null;
            $groupName = null;

            if ($state && $state['assignedByGroup'] !== null) {
                $source = 'Group';
                $groupId = $state['assignedByGroup'];
                
                // Optionally fetch group name
                try {
                    $groupResponse = \Illuminate\Support\Facades\Http::withToken($token)
                        ->get("{$this->baseUrl}/groups/{$groupId}", [
                            '$select' => 'displayName'
                        ]);
                    if ($groupResponse->successful()) {
                        $groupName = $groupResponse->json('displayName');
                    }
                } catch (\Exception $e) {
                    $groupName = 'Unknown Group (' . $groupId . ')';
                }
            }

            $licenses[] = [
                'sku_id' => $skuId,
                'sku_part_number' => $skuPartNumbers[$skuId] ?? null,
                'assignment_source' => $source,
                'assigned_by_group_id' => $groupId,
                'assigned_by_group_name' => $groupName,
                'disabled_plans' => $assignment['disabledPlans'] ?? [],
            ];
        }

        return [
            'user_principal_name' => $userData['userPrincipalName'],
            'display_name' => $userData['displayName'],
            'licenses' => $licenses,
        ];
    }
}
