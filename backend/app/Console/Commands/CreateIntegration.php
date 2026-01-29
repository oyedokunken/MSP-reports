<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Integration;
use App\Models\License;
use Illuminate\Support\Facades\Validator;

class CreateIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:create 
                            {--company-id= : The company ID}
                            {--type= : Integration type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively create a new integration for a company';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Create New Integration ===');
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

        // Step 2: Scan for available integrations
        $availableIntegrations = $this->scanAvailableIntegrations();
        
        if (empty($availableIntegrations)) {
            $this->error('No integrations found in app/Integrations directory!');
            return 1;
        }

        $availableTypes = array_keys($availableIntegrations);

        $integrationType = $this->option('type');
        if (!$integrationType) {
            // Build choice labels with descriptions
            $choices = array_map(function($type) use ($availableIntegrations) {
                $config = $availableIntegrations[$type];
                $displayName = $config['display_name'] ?? $type;
                $description = $config['description'] ?? '';
                return $description ? "{$displayName} - {$description}" : $displayName;
            }, $availableTypes);
            
            $selected = $this->choice(
                'Select integration type',
                $choices,
                0
            );
            
            // Extract type from selected choice
            $integrationType = $availableTypes[array_search($selected, $choices)];
        }
        
        // Get the integration config
        $integrationConfig = $availableIntegrations[$integrationType] ?? null;
        if (!$integrationConfig) {
            $this->error("Integration type '{$integrationType}' not found!");
            return 1;
        }

        // Check if integration already exists
        $existing = Integration::where('company_id', $company->company_id)
            ->where('integration_type', $integrationType)
            ->first();

        if ($existing) {
            $this->error("Integration '{$integrationType}' already exists for this company!");
            if (!$this->confirm('Do you want to view it?', true)) {
                return 1;
            }
            $this->displayIntegration($existing);
            return 0;
        }

        // Step 3: Check License
        $sku = 'integration_' . strtolower($integrationType);
        $hasLicense = License::where('company_id', $company->company_id)
            ->where('status', 'active')
            ->where(function ($q) use ($sku) {
                $q->where('product_sku', $sku)
                  ->orWhere('product_sku', 'unlimited');
            })
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>', now());
            })
            ->exists();

        if (!$hasLicense) {
            $this->warn("Warning: No active license found for '{$sku}'");
            if (!$this->confirm('Continue anyway?', false)) {
                return 1;
            }
        } else {
            $this->info("✓ License verified: {$sku}");
        }

        $this->newLine();

        // Step 4: Get Display Name
        $displayName = $this->ask(
            'Display name for this integration',
            "{$integrationType} - {$company->name}"
        );

        // Step 5: Collect Credentials (if any)
        $credentials = $this->getCredentialsFromConfig($integrationConfig);

        // Step 6: Integration-Specific Configuration
        $configuration = $this->getConfigurationFromConfig($integrationConfig);

        // Step 7: Set Initial Status
        $status = $this->choice(
            'Initial status',
            ['active', 'disconnected'],
            0
        );

        // Step 8: Create Integration
        $this->newLine();
        if (!$this->confirm('Create this integration?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $integration = Integration::create([
            'company_id' => $company->company_id,
            'integration_type' => $integrationType,
            'display_name' => $displayName,
            'status' => $status,
            'configuration' => $configuration,
            'credentials' => $credentials,
            'is_source_of_truth' => false,
        ]);

        $this->newLine();
        $this->info('✓ Integration created successfully!');
        $this->newLine();
        $this->displayIntegration($integration);

        // Step 9: For Active Directory, offer to create API key
        if ($integrationType === 'ActiveDirectory') {
            $this->newLine();
            if ($this->confirm('Would you like to create an API key for the AD agent?', true)) {
                $this->call('apikey:create', [
                    '--company-id' => $company->company_id,
                    '--name' => 'Active Directory Sync Agent',
                ]);
            }
        }

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

    /**
     * Scan available integrations from filesystem
     * @return array<string, array<string, mixed>>
     */
    protected function scanAvailableIntegrations(): array
    {
        $integrationsPath = app_path('Integrations');
        $integrations = [];

        if (!is_dir($integrationsPath)) {
            return [];
        }

        $directories = array_filter(glob($integrationsPath . '/*'), 'is_dir');

        foreach ($directories as $dir) {
            $integrationName = basename($dir);
            
            // Skip Contracts directory
            if ($integrationName === 'Contracts') {
                continue;
            }

            $configFile = $dir . '/config.php';
            
            if (file_exists($configFile)) {
                $config = require $configFile;
                $integrations[$integrationName] = $config;
            }
        }

        return $integrations;
    }

    /**
     * Get credentials from integration config file
     * @param array<string, mixed> $integrationConfig
     * @return array<string, mixed>
     */
    protected function getCredentialsFromConfig(array $integrationConfig): array
    {
        $credentials = [];

        if (empty($integrationConfig['credentials'])) {
            return $credentials;
        }

        $this->info('Credentials:');
        $this->newLine();

        foreach ($integrationConfig['credentials'] as $key => $fieldConfig) {
            $label = $fieldConfig['label'] ?? ucwords(str_replace('_', ' ', $key));
            $type = $fieldConfig['type'] ?? 'text';
            $required = $fieldConfig['required'] ?? false;
            $default = $fieldConfig['default'] ?? null;
            $help = $fieldConfig['help'] ?? null;
            $encrypted = $fieldConfig['encrypted'] ?? false;

            // Display help text if available
            if ($help) {
                $this->line("<comment>{$help}</comment>");
            }

            // Show encryption indicator
            if ($encrypted) {
                $this->line("<fg=yellow>🔒 This field will be encrypted in the database</>");
            }

            // Handle different field types
            switch ($type) {
                case 'select':
                    $options = $fieldConfig['options'] ?? [];
                    if (!empty($options)) {
                        $value = $this->choice($label, array_values($options), $default);
                        // Get the key for the selected value
                        $credentials[$key] = array_search($value, $options);
                    } else {
                        $credentials[$key] = $this->ask($label, $default);
                    }
                    break;

                case 'boolean':
                case 'checkbox':
                    $credentials[$key] = $this->confirm($label, $default ?? false);
                    break;

                case 'password':
                    if ($required) {
                        $credentials[$key] = $this->secret($label);
                    } else {
                        if ($this->confirm("Set {$label}?", false)) {
                            $credentials[$key] = $this->secret($label);
                        }
                    }
                    break;

                case 'text':
                case 'textarea':
                default:
                    if ($required && !$default) {
                        $credentials[$key] = $this->ask($label);
                    } else {
                        if ($this->confirm("Set {$label}?", $required)) {
                            $credentials[$key] = $this->ask($label, $default);
                        } elseif ($default) {
                            $credentials[$key] = $default;
                        }
                    }
                    break;
            }

            $this->newLine();
        }

        return $credentials;
    }

    /**
     * Get configuration from integration config file
     * @param array<string, mixed> $integrationConfig
     * @return array<string, mixed>
     */
    protected function getConfigurationFromConfig(array $integrationConfig): array
    {
        $config = [];

        if (empty($integrationConfig['configuration'])) {
            return $config;
        }

        $this->info('Configuration Options:');
        $this->newLine();

        foreach ($integrationConfig['configuration'] as $key => $fieldConfig) {
            $label = $fieldConfig['label'] ?? ucwords(str_replace('_', ' ', $key));
            $type = $fieldConfig['type'] ?? 'text';
            $required = $fieldConfig['required'] ?? false;
            $default = $fieldConfig['default'] ?? null;
            $help = $fieldConfig['help'] ?? null;

            // Display help text if available
            if ($help) {
                $this->line("<comment>{$help}</comment>");
            }

            // Handle different field types
            switch ($type) {
                case 'select':
                    $options = $fieldConfig['options'] ?? [];
                    if (!empty($options)) {
                        $value = $this->choice($label, array_values($options), $default);
                        // Get the key for the selected value
                        $config[$key] = array_search($value, $options);
                    } else {
                        $config[$key] = $this->ask($label, $default);
                    }
                    break;

                case 'boolean':
                case 'checkbox':
                    $config[$key] = $this->confirm($label, $default ?? false);
                    break;

                case 'number':
                    $value = $this->ask($label, $default);
                    $config[$key] = is_numeric($value) ? (int)$value : $value;
                    break;

                case 'password':
                    // For password fields, warn about security
                    $this->warn("Password field '{$label}' should be set securely via UI or direct database update");
                    if ($this->confirm("Set {$label} now? (not recommended)", false)) {
                        $config[$key] = $this->secret($label);
                    }
                    break;

                case 'text':
                case 'textarea':
                default:
                    if ($required && !$default) {
                        $config[$key] = $this->ask($label);
                    } else {
                        $config[$key] = $this->ask($label, $default);
                    }
                    break;
            }

            $this->newLine();
        }

        return $config;
    }

    /**
     * Display integration details
     */
    protected function displayIntegration(Integration $integration): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $integration->integration_id],
                ['Type', $integration->integration_type],
                ['Display Name', $integration->display_name],
                ['Company', $integration->company->name ?? 'N/A'],
                ['Status', $integration->status],
                ['Source of Truth', $integration->is_source_of_truth ? 'Yes' : 'No'],
                ['Last Sync', $integration->last_sync_at?->format('Y-m-d H:i:s') ?? 'Never'],
                ['Created', $integration->created_at->format('Y-m-d H:i:s')],
            ]
        );

        if (!empty($integration->configuration)) {
            $this->newLine();
            $this->info('Configuration:');
            foreach ($integration->configuration as $key => $value) {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $this->line("  {$key}: {$displayValue}");
            }
        }
    }
}
