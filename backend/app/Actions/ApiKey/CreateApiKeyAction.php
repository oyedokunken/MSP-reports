<?php

declare(strict_types=1);

namespace App\Actions\ApiKey;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateApiKeyAction
{
    /**
     * @param array{
     *     display_name: string,
     *     customer_id?: int|null,
     *     permissions?: array<string, mixed>|null,
     *     ip_whitelist?: array<string>|null,
     *     allowed_endpoints?: array<string>|null
     * } $data
     * @return array{apiKey: ApiKey, plainKey: string}
     */
    public function execute(User $user, array $data, string $ipAddress, string $userAgent): array
    {
        // Generate a secure random API key
        $plainKey = 'msp_' . Str::random(48);
        $keyHash = hash('sha256', $plainKey);
        
        // Save a preview (first 8 and last 4 characters)
        $keyPreview = substr($plainKey, 0, 8) . '...' . substr($plainKey, -4);

        $apiKey = ApiKey::create([
            'company_id' => $user->company_id,
            'customer_id' => $data['customer_id'] ?? null,
            'created_by_user_id' => $user->user_id,
            'display_name' => $data['display_name'],
            'key_preview' => $keyPreview,
            'key_hash' => $keyHash,
            'permissions' => $data['permissions'] ?? null,
            'ip_whitelist' => $data['ip_whitelist'] ?? null,
            'allowed_endpoints' => $data['allowed_endpoints'] ?? null,
            'revoked' => false,
        ]);

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_created',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $apiKey->api_key_id,
            'event_data' => [
                'display_name' => $apiKey->display_name,
                'customer_id' => $apiKey->customer_id,
                'has_ip_whitelist' => !empty($apiKey->ip_whitelist),
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return [
            'apiKey' => $apiKey,
            'plainKey' => $plainKey,
        ];
    }
}
