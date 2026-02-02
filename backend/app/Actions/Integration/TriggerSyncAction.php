<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\Company;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\IntegrationService;
use Illuminate\Validation\ValidationException;

final class TriggerSyncAction
{
    public function __construct(
        private readonly IntegrationService $integrationService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Company $company, int $integrationId): SyncLog
    {
        $integration = $company->integrations()->find($integrationId);

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => ['Integration not found for this company.'],
            ]);
        }

        return $this->integrationService->triggerSync($integration);
    }
}
