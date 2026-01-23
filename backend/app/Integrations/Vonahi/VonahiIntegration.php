<?php

namespace App\Integrations\Vonahi;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VonahiIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected VonahiClient $client;
    protected CustomerMappingService $mappingService;
    protected array $mappedDuringSync = [];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new VonahiClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            $organizations = $this->client->getOrganizations();
            
            Log::info('Vonahi Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'organization_count' => count($organizations),
            ]);

            foreach ($organizations as $org) {
                try {
                    $standardized = VonahiMapper::mapOrganization($org);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $orgId = $org['id'] ?? 'unknown';
                    $errors[] = "Failed to process organization {$orgId}: {$e->getMessage()}";
                }
            }

            // Sync pentests
            $pentests = $this->client->getPenTests();
            foreach ($pentests as $pentest) {
                try {
                    $pentestData = VonahiMapper::mapPenTest($pentest);
                    $this->storeHistoricalData($pentestData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process pentest: {$e->getMessage()}";
                }
            }

            // Sync findings
            $findings = $this->client->getSecurityFindings();
            foreach ($findings as $finding) {
                try {
                    $findingData = VonahiMapper::mapSecurityFinding($finding);
                    $this->storeHistoricalData($findingData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process finding: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('Vonahi Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->processUnmappedCustomers();

        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Log::info('Vonahi Integration: Sync completed', [
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

    private function processUnmappedCustomers(): array
    {
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
        }
        return [];
    }

    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        return array_map(fn($org) => VonahiMapper::mapOrganization($org), $organizations);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            $findings = $this->client->getSecurityFindings();
            
            foreach ($findings as $finding) {
                try {
                    $findingData = VonahiMapper::mapSecurityFinding($finding);
                    $data[] = $findingData;
                    $this->storeHistoricalData($findingData);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process finding: {$e->getMessage()}";
                }
            }

            $pentests = $this->client->getPenTests();
            foreach ($pentests as $pentest) {
                try {
                    $pentestData = VonahiMapper::mapPenTest($pentest);
                    $data[] = $pentestData;
                    $this->storeHistoricalData($pentestData);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process pentest: {$e->getMessage()}";
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

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'Vonahi',
            'description' => 'Vonahi vPenTest automated penetration testing',
            'data_types' => [
                'vonahi_organization',
                'vonahi_pentest',
                'vonahi_security_finding',
                'vonahi_scan',
                'vonahi_asset',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        Log::info('Vonahi Integration: Disconnected', [
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

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

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
            $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Vonahi');
        }
    }

    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $recordId = $payload['id'] ?? null;
        $dataType = $data['data_type'];
        
        if ($recordId) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $dataType)
                ->whereRaw("data_payload->>'id' = ?", [(string)$recordId])
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
                return;
            }
        }

        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $data['integration_customer_id'] ?? null,
            'data_type' => $dataType,
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $payload,
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'API URL',
                'type' => 'text',
                'required' => false,
                'default' => 'https://api.vpentest.io',
                'placeholder' => 'https://api.vpentest.io',
                'help' => 'Vonahi vPenTest API URL',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Vonahi API key',
                'help' => 'Your Vonahi Security API key from the dashboard',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('vonahi', 'Vonahi Security');
        $builder->description('Automated penetration testing and vulnerability assessment');
        
        $builder->addSection('metrics', 'Security Overview', 'grid')
            ->icon('Shield')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('total_pentests', 'Pen Tests', 'total_pentests', 'number', 'Search', 'blue')
            ->addMetric('critical_findings', 'Critical', 'critical_findings', 'number', 'AlertTriangle', 'red')
            ->addMetric('high_findings', 'High', 'high_findings', 'number', 'AlertCircle', 'orange')
            ->addMetric('total_findings', 'Total Findings', 'total_findings', 'number', 'FileWarning', 'purple')
            ->end();

        $builder->addSection('findings', 'Security Findings', 'table')
            ->description('Vulnerabilities discovered during penetration tests')
            ->icon('Bug')
            ->dataPath('findings')
            ->tableColumns([
                ['key' => 'title', 'label' => 'Finding', 'sortable' => true, 'filterable' => true],
                ['key' => 'asset', 'label' => 'Asset', 'sortable' => true],
                ['key' => 'cvss_score', 'label' => 'CVSS', 'sortable' => true],
                [
                    'key' => 'severity',
                    'label' => 'Severity',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Critical' => 'error',
                        'High' => 'warning',
                        'Medium' => 'info',
                        'Low' => 'success',
                    ],
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'open' => 'error',
                        'remediated' => 'success',
                        'accepted' => 'warning',
                    ],
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(25)
            ->end();
        
        return $builder->build();
    }
}
