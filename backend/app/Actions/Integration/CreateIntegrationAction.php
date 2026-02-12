<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\Integration;
use App\Services\IntegrationLifecycleService;
use App\Services\IntegrationService;
use App\Services\InAppNotificationService;
use Illuminate\Validation\ValidationException;

final class CreateIntegrationAction
{
    use LogsAudit;

    public function __construct(
        private readonly IntegrationService $integrationService,
        private readonly IntegrationLifecycleService $lifecycleService,
        private readonly InAppNotificationService $notificationService,
    ) {}

    /**
     * @param array{
     *   integration_type: string,
     *   display_name: string,
     *   status?: string,
     *   credentials?: array<string, mixed>|null,
     *   configuration?: array<string, mixed>|null,
     *   is_source_of_truth?: bool|null
     * } $data
     * @throws ValidationException
     */
    public function execute(Company $company, array $data, ?\App\Models\User $actor = null): Integration
    {
        $availableTypes = $this->integrationService->scanAvailableIntegrations();

        if (!in_array($data['integration_type'], $availableTypes, true)) {
            throw ValidationException::withMessages([
                'integration_type' => ['Invalid integration type.'],
            ]);
        }

        $data['status'] = $data['status'] ?? 'active';

        $integration = $this->lifecycleService->onboardNewIntegration($company, $data);

        if ($actor) {
            $this->notificationService->integrationCreated($integration, $actor);
        }

        $this->audit($company->company_id, 'integration_created', $integration, [
            'integration_name' => $integration->display_name,
            'integration_type' => $integration->integration_type,
        ]);

        return $integration;
    }
}
