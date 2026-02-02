<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\Company;
use App\Models\Integration;
use Illuminate\Validation\ValidationException;

final class UpdateIntegrationAction
{
    /**
     * @param array{
     *   display_name?: string,
     *   status?: string,
     *   credentials?: array<string, mixed>,
     *   configuration?: array<string, mixed>
     * } $data
     * @throws ValidationException
     */
    public function execute(Company $company, int $integrationId, array $data): Integration
    {
        $integration = $company->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => ['Integration not found for this company.'],
            ]);
        }

        if (isset($data['display_name'])) {
            $integration->display_name = trim(strip_tags($data['display_name']));
        }

        if (array_key_exists('status', $data)) {
            $integration->status = $data['status'];
        }

        if (array_key_exists('credentials', $data)) {
            $integration->credentials = $data['credentials'];
        }

        if (array_key_exists('configuration', $data)) {
            $integration->configuration = $data['configuration'];
        }

        $integration->save();

        return $integration;
    }
}
