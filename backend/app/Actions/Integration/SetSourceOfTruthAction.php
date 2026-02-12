<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\Integration;
use Illuminate\Validation\ValidationException;

final class SetSourceOfTruthAction
{
    use LogsAudit;

    /**
     * @throws ValidationException
     * @return array{integration: Integration, message: string}
     */
    public function execute(Company $company, int $integrationId): array
    {
        $integration = $company->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration' => ['Integration not found for this company.'],
            ]);
        }

        // Clear existing source of truth
        $company->integrations()->update(['is_source_of_truth' => false]);
        $integration->update(['is_source_of_truth' => true]);

        // Update company settings
        $settings = $company->settings ?? [];
        if (!isset($settings['integrations'])) {
            $settings['integrations'] = [];
        }
        $settings['integrations']['source_of_truth_integration_id'] = $integration->integration_id;
        $company->settings = $settings;
        $company->save();

        $this->audit($company->company_id, 'integration_source_of_truth_changed', $integration, [
            'integration_name' => $integration->display_name,
            'integration_type' => $integration->integration_type,
        ]);

        return [
            'integration' => $integration,
            'message' => 'Source of truth integration updated successfully.',
        ];
    }
}
