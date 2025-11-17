<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Str;

class CreateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apikey:create 
                            {--company-id= : The company ID}
                            {--name= : Display name for the API key}
                            {--integrations-sync : Grant integrations sync permission}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively create a new API key for a company';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Create New API Key ===');
        $this->newLine();

        // Step 1: Select Company
        $companyId = $this->option('company-id');
        if (!$companyId) {
            $company = $this->selectCompany();
        } else {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("Company with ID {$companyId} not found.");
                return 1;
            }
        }

        $this->info("Selected Company: {$company->name} (ID: {$company->company_id})");
        $this->newLine();

        // Step 2: Display Name
        $displayName = $this->option('name');
        if (!$displayName) {
            $displayName = $this->ask('Display name for this API key', 'API Key');
        }

        // Step 3: Set Permissions
        $this->info('Configure Permissions:');
        $this->newLine();

        $permissions = [];

        // Integrations permissions
        if ($this->option('integrations-sync')) {
            $permissions['integrations'] = [
                'view' => true,
                'manage' => false,
                'sync' => true,
            ];
        } else {
            if ($this->confirm('Allow integration sync?', true)) {
                $permissions['integrations']['view'] = $this->confirm('  View integrations?', true);
                $permissions['integrations']['manage'] = $this->confirm('  Manage integrations?', false);
                $permissions['integrations']['sync'] = $this->confirm('  Trigger syncs?', true);
            }
        }

        // Customers permissions
        if ($this->confirm('Allow customer access?', false)) {
            $permissions['customers']['view'] = $this->confirm('  View customers?', true);
            $permissions['customers']['edit'] = $this->confirm('  Edit customers?', false);
            $permissions['customers']['map'] = $this->confirm('  Map customers?', false);
        }

        // Reports permissions
        if ($this->confirm('Allow report access?', false)) {
            $permissions['reports']['view'] = $this->confirm('  View reports?', true);
            $permissions['reports']['configure'] = $this->confirm('  Configure reports?', false);
            $permissions['reports']['generate'] = $this->confirm('  Generate reports?', false);
            $permissions['reports']['download'] = $this->confirm('  Download reports?', true);
        }

        // Users and company settings (usually false for API keys)
        $permissions['users'] = [
            'view' => false,
            'manage' => false,
        ];
        $permissions['company_settings'] = [
            'view' => false,
            'edit' => false,
        ];

        $this->newLine();

        // Step 4: IP Whitelist (optional)
        $ipWhitelist = null;
        if ($this->confirm('Configure IP whitelist?', false)) {
            $ips = $this->ask('Enter allowed IP addresses (comma-separated)');
            $ipWhitelist = array_map('trim', explode(',', $ips));
        }

        // Step 5: Allowed Endpoints (optional)
        $allowedEndpoints = null;
        if ($this->confirm('Restrict to specific endpoints?', false)) {
            $this->info('Examples: api/integrations/*, api/integrations/active-directory/push');
            $endpoints = $this->ask('Enter allowed endpoints (comma-separated)');
            $allowedEndpoints = array_map('trim', explode(',', $endpoints));
        }

        // Step 6: Review and Create
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Company', $company->name],
                ['Display Name', $displayName],
                ['Integrations Sync', $permissions['integrations']['sync'] ?? false ? 'Yes' : 'No'],
                ['IP Whitelist', $ipWhitelist ? implode(', ', $ipWhitelist) : 'None'],
                ['Endpoint Restrictions', $allowedEndpoints ? implode(', ', $allowedEndpoints) : 'None'],
            ]
        );

        $this->newLine();
        if (!$this->confirm('Create this API key?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        // Generate API key
        $plainTextKey = 'msp_' . Str::random(64);
        $keyHash = hash('sha256', $plainTextKey);

        // Get system user for created_by
        $systemUser = User::where('company_id', $company->company_id)->first();

        // Create API key
        $apiKey = ApiKey::create([
            'company_id' => $company->company_id,
            'created_by_user_id' => $systemUser?->user_id,
            'display_name' => $displayName,
            'key_hash' => $keyHash,
            'permissions' => $permissions,
            'ip_whitelist' => $ipWhitelist,
            'allowed_endpoints' => $allowedEndpoints,
            'revoked' => false,
        ]);

        // Display results
        $this->newLine();
        $this->info('✓ API Key created successfully!');
        $this->newLine();

        $this->warn('IMPORTANT: This key will only be shown ONCE. Store it securely!');
        $this->newLine();

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("API Key: {$plainTextKey}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['Key ID', $apiKey->api_key_id],
                ['Display Name', $apiKey->display_name],
                ['Company', $company->name],
                ['Created', $apiKey->created_at->format('Y-m-d H:i:s')],
            ]
        );

        // Provide usage examples
        $this->newLine();
        $this->info('Usage Examples:');
        $this->newLine();

        if (isset($permissions['integrations']['sync']) && $permissions['integrations']['sync']) {
            $this->line('Active Directory Agent (config.json):');
            $this->line('  "platform": {');
            $this->line('    "apiUrl": "https://your-platform-url/api",');
            $this->line('    "apiKey": "' . $plainTextKey . '",');
            $this->line('    "companyId": ' . $company->company_id);
            $this->line('  }');
            $this->newLine();
        }

        $this->line('cURL Request:');
        $this->line('  curl -X POST https://your-platform-url/api/integrations/active-directory/push \\');
        $this->line('    -H "Authorization: Bearer ' . $plainTextKey . '" \\');
        $this->line('    -H "Content-Type: application/json" \\');
        $this->line('    -d \'{"companyId": ' . $company->company_id . ', ...}\'');

        return 0;
    }

    /**
     * Select a company interactively
     */
    protected function selectCompany(): Company
    {
        $companies = Company::all(['company_id', 'name', 'status']);

        if ($companies->isEmpty()) {
            $this->error('No companies found in the database!');
            exit(1);
        }

        if ($companies->count() === 1) {
            return $companies->first();
        }

        $choices = $companies->map(function ($company) {
            return "{$company->name} (ID: {$company->company_id}) - {$company->status}";
        })->toArray();

        $selected = $this->choice('Select a company', $choices);

        // Extract company ID from selection
        preg_match('/\(ID: (\d+)\)/', $selected, $matches);
        $companyId = $matches[1];

        return Company::find($companyId);
    }
}
