<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\Company;
use App\Models\User;
use App\Services\InAppNotificationService;
use Illuminate\Validation\ValidationException;

final class DeleteIntegrationAction
{
    public function __construct(
        private readonly InAppNotificationService $notificationService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Company $company, int $integrationId, ?User $actor = null): void
    {
        $integration = $company->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => ['Integration not found for this company.'],
            ]);
        }

        if ($integration->is_source_of_truth) {
            throw ValidationException::withMessages([
                'integration' => ['Cannot delete source of truth integration. Please designate another integration as source of truth first.'],
            ]);
        }

        $integrationName = $integration->display_name;
        $integration->delete();

        if ($actor) {
            $this->notificationService->integrationDeleted($integrationName, $company, $actor);
        }
    }
}
