<?php

declare(strict_types=1);

namespace App\Actions\ApiKey;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DeleteApiKeyAction
{
    public function execute(User $user, int $apiKeyId, string $ipAddress, string $userAgent): void
    {
        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $apiKeyId)
            ->first();

        if (!$apiKey) {
            throw ValidationException::withMessages([
                'api_key' => ['API key not found or access denied.'],
            ]);
        }

        $displayName = $apiKey->display_name;
        $apiKey->delete();

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_deleted',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $apiKeyId,
            'event_data' => [
                'display_name' => $displayName,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
