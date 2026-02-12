<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Concerns\LogsAudit;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

final class UpdateSettingsAction
{
    use LogsAudit;

    /**
     * @param array{
     *   name?: string,
     *   abn?: string|null,
     *   contact_email?: string|null,
     *   contact_phone?: string|null,
     *   address?: string|null,
     *   settings?: array<string, mixed>|null
     * } $data
     */
    public function execute(Company $company, array $data, int $userId): Company
    {
        $company->update($data);

        Log::info('Company settings updated', [
            'company_id' => $company->company_id,
            'updated_fields' => array_keys($data),
            'user_id' => $userId,
        ]);

        $this->audit($company->company_id, 'settings_updated', $company, [
            'updated_fields' => array_keys($data),
        ]);

        return $company;
    }
}
