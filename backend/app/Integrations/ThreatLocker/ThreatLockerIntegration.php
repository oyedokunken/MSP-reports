<?php

declare(strict_types=1);

namespace App\Integrations\ThreatLocker;
use App\Integrations\Concerns\HasHeartbeat;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class ThreatLockerIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    protected Integration $integration;
    protected ThreatLockerClient $client;
    protected CustomerMappingService $mappingService;
    /** @var array<int, mixed> */
    protected array $mappedDuringSync = [];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new ThreatLockerClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            // Get all organizations from ThreatLocker
            $organizations = $this->client->getOrganizations();
            
            Log::info('ThreatLocker Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'organization_count' => count($organizations),
            ]);

            foreach ($organizations as $org) {
                try {
                    $standardized = ThreatLockerMapper::mapOrganization($org);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $orgId = $org['id'] ?? $org['organizationId'] ?? 'unknown';
                    $errors[] = "Failed to process organization {$orgId}: {$e->getMessage()}";
                    Log::error('ThreatLocker Integration: Failed to process organization', [
                        'integration_id' => $this->integration->integration_id,
                        'organization_id' => $orgId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('ThreatLocker Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Process unmapped customers
        $this->processUnmappedCustomers();

        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Log::info('ThreatLocker Integration: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $this->mappedDuringSync,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function processUnmappedCustomers(): array
    {
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            Log::info('ThreatLocker: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        return array_map(fn($org) => ThreatLockerMapper::mapOrganization($org), $organizations);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get security events
            $events = $this->client->getSecurityEvents(null, $startDate);
            
            foreach ($events as $event) {
                try {
                    $standardized = ThreatLockerMapper::mapSecurityEvent($event);
                    $data[] = $standardized;
                    $this->storeHistoricalData($standardized);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process security event: {$e->getMessage()}";
                }
            }

            // Get application blocks
            $blocks = $this->client->getApplicationBlocks(null, $startDate);
            
            foreach ($blocks as $block) {
                try {
                    $standardized = ThreatLockerMapper::mapApplicationBlock($block);
                    $data[] = $standardized;
                    $this->storeHistoricalData($standardized);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process application block: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
        }

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): Carbon
    {
        return now()->subDays(90);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'ThreatLocker',
            'description' => 'ThreatLocker application allowlisting and ringfencing',
            'data_types' => [
                'threatlocker_organization',
                'threatlocker_computer',
                'threatlocker_policy',
                'threatlocker_security_event',
                'threatlocker_application_block',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        Log::info('ThreatLocker Integration: Disconnected', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array<string, int>
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function storeCustomer(array $customer): void
    {
        $integrationCustomer = IntegrationCustomer::updateOrCreate(
            [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $customer['external_id'],
            ],
            [
                'name' => $customer['name'],
                'metadata' => $customer['metadata'],
                'active' => $customer['active'],
                'last_seen_at' => now(),
            ]
        );

        $integrationCustomer->refresh();

        if (!$integrationCustomer->customer_id && $this->integration->is_source_of_truth) {
            $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'ThreatLocker');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $recordId = $payload['id'] ?? $payload['eventId'] ?? null;
        
        if ($recordId) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $data['data_type'])
                ->whereRaw("data_payload->>'id' = ?", [(string)$recordId])
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
            } else {
                IntegrationData::create([
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $data['integration_customer_id'] ?? null,
                    'data_type' => $data['data_type'],
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                    'data_version' => 1,
                ]);
            }
        } else {
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $data['integration_customer_id'] ?? null,
                'data_type' => $data['data_type'],
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $payload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your ThreatLocker API key',
                'help' => 'Your ThreatLocker API key from the admin portal',
            ],
            [
                'name' => 'organization_id',
                'label' => 'Organization ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your Organization ID',
                'help' => 'Your ThreatLocker organization identifier',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('threatlocker', 'ThreatLocker');
        $builder->description('Application allowlisting and ringfencing');
        
        $builder->addSection('status', 'Integration Status', 'grid')
            ->icon('Lock')
            ->dataPath('status')
            ->gridColumns(2)
            ->addMetric('status', 'Status', 'status', 'text', 'CheckCircle', 'blue')
            ->addMetric('message', 'Message', 'message', 'text', 'Info', 'gray')
            ->end();
        
        return $builder->build();
    }
}
