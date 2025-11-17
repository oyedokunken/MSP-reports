<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Integration;

class ListIntegrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:list {--company-id= : Filter by company ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all integrations or filter by company';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companyId = $this->option('company-id');

        $query = Integration::with('company');

        if ($companyId) {
            $query->where('company_id', $companyId);
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("Company with ID {$companyId} not found.");
                return 1;
            }
            $this->info("Integrations for: {$company->name}");
        } else {
            $this->info('All Integrations');
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->warn('No integrations found.');
            return 0;
        }

        $this->newLine();

        $rows = $integrations->map(function ($integration) {
            return [
                $integration->integration_id,
                $integration->integration_type,
                $integration->display_name,
                $integration->company->name ?? 'N/A',
                $this->getStatusEmoji($integration->status) . ' ' . $integration->status,
                $integration->is_source_of_truth ? '✓' : '',
                $integration->last_sync_at?->diffForHumans() ?? 'Never',
                $integration->last_sync_status ?? 'N/A',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Type', 'Display Name', 'Company', 'Status', 'SoT', 'Last Sync', 'Sync Status'],
            $rows
        );

        $this->newLine();
        $this->info('Total: ' . $integrations->count());

        return 0;
    }

    /**
     * Get emoji for status
     */
    protected function getStatusEmoji(string $status): string
    {
        return match($status) {
            'active' => '✓',
            'disconnected' => '○',
            'error' => '✗',
            'importing_historical' => '↻',
            default => '?',
        };
    }
}
