<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Integrations\M365\M365Integration;
use App\Services\M365LicenseService;

class CheckM365UserLicensing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'm365:user-licensing {upn} {--integration= : Integration ID to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Determine licenses assigned to a Microsoft 365 user (Direct vs Group)';

    /**
     * Execute the console command.
     */
    public function handle(M365LicenseService $licenseService)
    {
        $upn = $this->argument('upn');
        $integrationId = $this->option('integration');

        $query = Integration::where('integration_type', 'M365')
            ->where('status', 'active');

        if ($integrationId) {
            $query->where('integration_id', $integrationId);
        }

        $integrationModel = $query->first();

        if (!$integrationModel) {
            $this->error('No active M365 integration found.');
            return 1;
        }

        $this->info("Checking licensing for {$upn} using integration #{$integrationModel->integration_id} ({$integrationModel->display_name})");

        try {
            $m365 = new M365Integration($integrationModel);
            $data = $m365->getUserLicensing($upn);

            $this->info("User: {$data['display_name']} ({$data['user_principal_name']})");

            if (empty($data['licenses'])) {
                $this->warn('No licenses assigned to this user.');
                return 0;
            }

            $headers = ['License (Friendly Name)', 'SKU Part Number', 'Source', 'Group Name'];
            $rows = [];

            foreach ($data['licenses'] as $license) {
                $rows[] = [
                    $licenseService->getFriendlyName($license['sku_id'], $license['sku_part_number']),
                    $license['sku_part_number'] ?? 'N/A',
                    $license['assignment_source'],
                    $license['assigned_by_group_name'] ?? 'N/A',
                ];
            }

            $this->table($headers, $rows);

        } catch (\Exception $e) {
            $this->error('Failed to retrieve licensing: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
