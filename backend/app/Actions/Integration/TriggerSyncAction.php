<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\IntegrationService;
use App\Services\InAppNotificationService;
use Illuminate\Validation\ValidationException;

final class TriggerSyncAction
{
    use LogsAudit;

    public function __construct(
        private readonly IntegrationService $integrationService,
        private readonly InAppNotificationService $notificationService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Company $company, int $integrationId, ?User $actor = null): SyncLog
    {
        $integration = $company->integrations()->find($integrationId);

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => ['Integration not found for this company.'],
            ]);
        }

        $syncLog = $this->integrationService->triggerSync($integration);

        if ($actor) {
            $this->notificationService->syncStarted($integration, $actor);
        }

        $this->audit($company->company_id, 'integration_synced', $integration, [
            'integration_name' => $integration->display_name,
            'sync_id' => $syncLog->sync_id,
        ]);

        return $syncLog;
    }
}
