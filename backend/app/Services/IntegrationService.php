<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\Company;
use App\Models\SyncLog;
use App\Services\SyncEngine;
use App\Services\LicenseChecker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IntegrationService
{
    protected SyncEngine $syncEngine;
    protected LicenseChecker $licenseChecker;

    public function __construct(SyncEngine $syncEngine, Lic enseChecker $licenseChecker)
    {
        $this->syncEngine = $syncEngine;
        $this->licenseChecker = $licenseChecker;
    }

    /**
     * Scan available integrations from filesystem
     */
    public function scanAvailableIntegrations(): array
    {
        $integrationsPath = app_path('Integrations');
        $integrations = [];

        if (!is_dir($integrationsPath)) {
            return [];
        }

        $directories = array_filter(glob($integrationsPath . '/*'), 'is_dir');

        foreach ($directories as $dir) {
            $integrationName = basename($dir);
            
            if ($integrationName === 'Contracts') {
                continue;
            }

            $configFile = $dir . '/config.php';
            if (file_exists($configFile)) {
                $integrations[] = $integrationName;
            }
        }

        return $integrations;
    }

    /**
     * Get metadata and credential fields for a specific integration type
     */
    public function getIntegrationTypeConfig(string $integrationType): array
    {
        $className = "App\\Integrations\\{$integrationType}\\{$integrationType}Integration";
        if (!class_exists($className)) {
            throw new \Exception("Integration type '{$integrationType}' not found.");
        }

        $tempIntegration = new Integration([
            'integration_type' => $integrationType,
            'credentials' => [],
        ]);
        $instance = new $className($tempIntegration);
        
        $metadata = $instance->getMetadata();
        $credentialFields = $instance->getCredentialFields();

        return [
            'name' => $metadata['name'] ?? $integrationType,
            'description' => $metadata['description'] ?? '',
            'credential_fields' => $credentialFields,
            'data_types' => $metadata['data_types'] ?? [],
            'supports_historical' => $metadata['supports_historical'] ?? false,
            'historical_range_days' => $metadata['historical_range_days'] ?? 0,
        ];
    }

    /**
     * Create a new integration for a company
     */
    public function createIntegration(Company $company, array $data): Integration
    {
        $integrationType = $data['integration_type'];
        
        // 1. License Check
        $sku = 'integration_' . strtolower($integrationType);
        if (!$this->licenseChecker->hasLicense($company, $sku)) {
             throw new \Exception("Company does not have an active license for this integration type.");
        }

        // 2. Prevent duplicates (except SharePoint)
        if ($integrationType !== 'SharePoint') {
            $exists = $company->integrations()->where('integration_type', $integrationType)->exists();
            if ($exists) {
                throw new \Exception("Integration already exists for this company.");
            }
        }

        $credentials = $this->prepareCredentials($integrationType, $data['credentials'] ?? []);
        $status = $data['status'] ?? 'disconnected';

        // 3. Test Authentication
        if (!empty($credentials) && $status !== 'disconnected') {
            $this->testAuthentication($integrationType, $company, $credentials, $data['display_name'], $data['configuration'] ?? []);
            $status = 'active';
        }

        // 4. Determine Source of Truth
        $isFirst = $company->integrations()->count() === 0;
        $isSourceOfTruth = $isFirst ? true : ($data['is_source_of_truth'] ?? false);
        $syncSchedule = $this->getDefaultSchedule($integrationType);

        return DB::transaction(function () use ($company, $integrationType, $data, $credentials, $status, $isSourceOfTruth, $syncSchedule) {
            $integration = $company->integrations()->create([
                'integration_type' => $integrationType,
                'display_name' => $data['display_name'],
                'status' => $status,
                'credentials' => $credentials,
                'configuration' => $data['configuration'] ?? null,
                'is_source_of_truth' => $isSourceOfTruth,
                'sync_schedule' => $syncSchedule,
            ]);

            if ($isSourceOfTruth) {
                $this->updateCompanySourceOfTruth($company, $integration->integration_id);
            }

            return $integration;
        });
    }

    protected function prepareCredentials(string $type, array $credentials): array
    {
        $credentials = array_filter($credentials, fn($v) => $v !== null && $v !== '');

        switch ($type) {
            case 'Datto':
                if (isset($credentials['rmm_region'])) {
                    $credentials['rmm_url'] = $credentials['rmm_region'];
                    unset($credentials['rmm_region']);
                } elseif (!isset($credentials['rmm_url']) && (isset($credentials['rmm_api_key']) || isset($credentials['rmm_api_secret']))) {
                    $credentials['rmm_url'] = 'https://syrah-api.centrastage.net';
                }
                break;
            case 'LibreNMS':
                if (!isset($credentials['base_url']) && isset($credentials['api_token'])) {
                    $credentials['base_url'] = 'http://192.168.228.7/api/v0';
                }
                if (isset($credentials['base_url'])) {
                    $credentials['base_url'] = rtrim($credentials['base_url'], '/');
                }
                break;
            case 'Autotask':
                if (isset($credentials['zone'])) {
                    $credentials['endpoint'] = $credentials['zone'];
                    unset($credentials['zone']);
                }
                break;
            case 'Sendmarc':
                $credentials['base_url'] = $credentials['base_url'] ?? 'https://portal.sendmarc.com/api/v1';
                break;
            case 'Barracuda':
                $credentials['base_url'] = $credentials['base_url'] ?? 'https://api.barracuda.com';
                break;
            case 'BitDefender':
                $credentials['api_url'] = $credentials['api_url'] ?? 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc';
                break;
        }

        return $credentials;
    }

    protected function testAuthentication(string $type, Company $company, array $credentials, string $displayName, array $configuration): void
    {
        $className = "App\\Integrations\\{$type}\\{$type}Integration";
        if (!class_exists($className)) {
            throw new \Exception("Integration class not found.");
        }

        $tempIntegration = new Integration([
            'company_id' => $company->company_id,
            'integration_type' => $type,
            'display_name' => $displayName,
            'status' => 'disconnected',
            'credentials' => $credentials,
            'configuration' => $configuration,
        ]);

        $instance = new $className($tempIntegration);
        if (!$instance->authenticate()) {
             throw new \Exception("Authentication failed. Please verify your credentials.");
        }
    }

    protected function updateCompanySourceOfTruth(Company $company, int $integrationId): void
    {
        $company->integrations()->where('integration_id', '!=', $integrationId)->update(['is_source_of_truth' => false]);
        
        $settings = $company->settings ?? [];
        $settings['integrations']['source_of_truth_integration_id'] = $integrationId;
        $company->settings = $settings;
        $company->save();
    }

    public function triggerSync(Integration $integration, string $priority = 'high'): SyncLog
    {
        if ($integration->syncLogs()->whereIn('status', ['queued', 'running'])->exists()) {
             throw new \Exception("A sync is already in progress for this integration.");
        }

        return $this->syncEngine->triggerSync($integration, 'current_state', $priority);
    }

    public function calculateNextSyncAt(Integration $integration): ?string
    {
        if (!$integration->last_sync_at || $integration->status !== 'active') {
            return null;
        }

        if ($integration->last_sync_status === 'syncing') {
            return now()->subSeconds(5)->toISOString();
        }

        $syncSchedule = $integration->sync_schedule ?? $this->getDefaultSchedule($integration->integration_type);
        $intervalMinutes = $this->parseCronInterval($syncSchedule);
        
        if ($intervalMinutes === null) {
            return null;
        }

        return $integration->last_sync_at->copy()->addMinutes($intervalMinutes)->toISOString();
    }

    public function parseCronInterval(string $cronSchedule): ?int
    {
        if (preg_match('/^\*\/(\d+)\s+\*/', $cronSchedule, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/^0\s+\*\/(\d+)/', $cronSchedule, $matches)) {
            return (int)$matches[1] * 60;
        }

        $patterns = [
            '0 */1 * * *' => 60,
            '0 */2 * * *' => 120,
            '0 */4 * * *' => 240,
            '0 */6 * * *' => 360,
            '0 */12 * * *' => 720,
            '0 0 * * *' => 1440,
        ];

        return $patterns[$cronSchedule] ?? null;
    }

    public function getDefaultSchedule(string $integrationType): string
    {
        return match($integrationType) {
            'Datto' => '*/5 * * * *',
            'LibreNMS', 'Autotask' => '*/10 * * * *',
            'ITGlue', 'SaaS', 'BitDefender', 'Sendmarc', 'Barracuda' => '0 */6 * * *',
            default => '0 */6 * * *',
        };
    }
}
