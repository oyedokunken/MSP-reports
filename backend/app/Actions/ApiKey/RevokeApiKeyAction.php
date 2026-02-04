<?php

declare(strict_types=1);

namespace App\Actions\ApiKey;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RevokeApiKeyAction
{
    public function execute(User $user, int $apiKeyId, string $ipAddress, string $userAgent): ApiKey
    {
        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $apiKeyId)
            ->first();

        if (!$apiKey) {
            throw ValidationException::withMessages([
                'api_key' => ['API key not found or access denied.'],
            ]);
        }

        if ($apiKey->revoked) {
            throw ValidationException::withMessages([
                'api_key' => ['API key is already revoked.'],
            ]);
        }

        $apiKey->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->user_id,
        ]);

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_revoked',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $apiKey->api_key_id,
            'event_data' => [
                'display_name' => $apiKey->display_name,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return $apiKey;
    }
}
