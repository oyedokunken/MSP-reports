<?php

namespace App\Integrations\Sendmarc;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SendmarcIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected SendmarcClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new SendmarcClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_token',
                'label' => 'API Token',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Sendmarc API token',
                'help' => 'Found in Sendmarc under Settings > API Tokens',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    protected $mappedDuringSync = [];

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            $accounts = $this->client->getAccounts();
            
            \Log::info('Sendmarc sync: Retrieved accounts', [
                'count' => count($accounts),
                'integration_id' => $this->integration->integration_id,
            ]);

            foreach ($accounts as $account) {
                $accountUuid = $account['uuid'] ?? null;
                
                if (!$accountUuid) {
                    \Log::warning('Sendmarc sync: Account missing UUID', ['account' => $account]);
                    continue;
                }

                $domains = $this->client->getAccountDomains($accountUuid);
                
                \Log::info('Sendmarc sync: Retrieved domains for account', [
                    'account_uuid' => $accountUuid,
                    'domain_count' => count($domains),
                ]);

                collect($domains)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed) {
                    foreach ($chunk as $domain) {
                        try {
                            $standardized = SendmarcMapper::mapDomain($domain);

                            $this->storeCustomer($standardized);
                            $totalProcessed++;

                        } catch (\Exception $e) {
                            $domainName = $domain['domain_name'] ?? 'unknown';
                            $errors[] = "Failed to process domain {$domainName}: {$e->getMessage()}";
                            \Log::error('Sendmarc sync: Failed to process domain', [
                                'integration_id' => $this->integration->integration_id,
                                'domain' => $domainName,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    
                    \Log::info('Sendmarc sync: Processed chunk', [
                        'integration_id' => $this->integration->integration_id,
                        'processed' => $totalProcessed,
                    ]);
                });
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            \Log::error('Sendmarc sync: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $additionalMapped = $this->processUnmappedCustomers();
        $mappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey);
        
        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Cache::put($cacheKey, [
            'mapped' => $mappedCount,
            'unmapped' => $unmappedCount,
            'last_sync' => now(),
        ], now()->addMinutes(10));

        \Log::info('Sendmarc sync: Completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $totalProcessed,
            'records_new' => $unmappedCount,
            'customers_mapped' => $mappedCustomers,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        // Dispatch single batch job to process all unmapped customers
        BatchAutoMatchCustomersJob::dispatch($this->integration);
        return []; // Batch job handles mapping asynchronously
    }

    public function getCustomers(): array
    {
        $allDomains = [];
        
        try {
            $accounts = $this->client->getAccounts();
            
            foreach ($accounts as $account) {
                $accountUuid = $account['uuid'] ?? null;
                if (!$accountUuid) continue;
                
                $domains = $this->client->getAccountDomains($accountUuid);
                $allDomains = array_merge($allDomains, $domains);
            }
            
        } catch (\Exception $e) {
            \Log::error('Sendmarc getCustomers failed', ['error' => $e->getMessage()]);
        }

        return array_map(fn($domain) => SendmarcMapper::mapDomain($domain), $allDomains);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // NO MORE $data = [] bucket! Just track stats
        $errors = [];
        $totalProcessed = 0;

        try {
            $accounts = $this->client->getAccounts();

            foreach ($accounts as $account) {
                $accountUuid = $account['uuid'] ?? null;
                if (!$accountUuid) continue;

                $domains = $this->client->getAccountDomains($accountUuid);

                collect($domains)->chunk(50)->each(function ($chunk) use ($accountUuid, &$errors, &$totalProcessed) {
                    foreach ($chunk as $domain) {
                        $domainUuid = $domain['uuid'] ?? null;
                        if (!$domainUuid) continue;

                        try {
                            // 1. Fetch senders
                            $senders = $this->client->getDomainSenders($accountUuid, $domainUuid);
                            // 2. Process IMMEDIATELY (no accumulation)
                            foreach ($senders as $sender) {
                                $standardized = SendmarcMapper::mapSenderStatistics($sender, $domainUuid);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            }
                            // 3. $senders is freed when loop ends

                            // Same pattern for volume totals
                            $volumeTotals = $this->client->getDomainVolumeTotals($accountUuid, $domainUuid);
                            if (!empty($volumeTotals)) {
                                $standardized = SendmarcMapper::mapVolumeTotals($volumeTotals, $domainUuid);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            }

                            // Same pattern for volume timeline
                            $volumeTimeline = $this->client->getDomainVolumeTimeline($accountUuid, $domainUuid);
                            foreach ($volumeTimeline as $index => $timelineEntry) {
                                $standardized = SendmarcMapper::mapVolumeTimeline($timelineEntry, $domainUuid, $index);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            }

                            // Same pattern for IP addresses
                            $ipAddresses = $this->client->getDomainIpAddresses($accountUuid, $domainUuid);
                            foreach ($ipAddresses as $ipData) {
                                $standardized = SendmarcMapper::mapIpAddressStatistics($ipData, $domainUuid);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            }

                        } catch (\Exception $e) {
                            $errors[] = "Failed to process domain historical data: {$e->getMessage()}";
                            \Log::error('Sendmarc historical: Failed to process domain', [
                                'integration_id' => $this->integration->integration_id,
                                'domain_uuid' => $domainUuid,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
            }

            \Log::info('Sendmarc historical: Completed', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            \Log::error('Sendmarc historical: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(90);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'Sendmarc',
            'description' => 'Sendmarc DMARC Email Security',
            'data_types' => [
                'domains',
                'sender_statistics',
                'volume_totals',
                'volume_timeline',
                'ip_address_statistics',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        // Clean up any cached data or sessions
        // Sendmarc uses Bearer token auth, so no session to destroy
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

        if (!$integrationCustomer->customer_id) {
            if ($this->integration->is_source_of_truth) {
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Sendmarc');
            } else {
                // Batch job processes all unmapped customers at end of sync (in processUnmappedCustomers)
            }
        }
    }

    private function storeHistoricalData(array $data): void
    {
        $domainUuid = $data['payload']['domain_uuid'] ?? null;
        
        if (!$domainUuid) {
            \Log::warning('Sendmarc historical: Missing domain UUID', ['data' => $data]);
            return;
        }
        
        $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereJsonContains('metadata->uuid', $domainUuid)
            ->first();

        if (!$integrationCustomer) {
            \Log::warning('Sendmarc historical: Integration customer not found', [
                'domain_uuid' => $domainUuid
            ]);
            return;
        }

        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'data_type' => $data['data_type'],
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $data['payload'],
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }
}
