<?php

declare(strict_types=1);

namespace App\Integrations\Sendmarc;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SendmarcIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_token',
                'label' => 'API Token',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your API Token',
                'help' => 'Found in Sendmarc under Settings > API Tokens',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /** @var array<int, mixed> */
    protected $mappedDuringSync = [];

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
             // 1. Checkpoint: Resume
            $state = json_decode($this->integration->sync_state ?? '{}', true);
            $phase = $state['phase'] ?? 'domains';
            $resumeOffset = $state['offset'] ?? 0;
            
            $accounts = $this->client->getAccounts();
            
            if ($phase === 'domains') {
                $allDomains = [];
                foreach ($accounts as $account) {
                    $accountUuid = $account['uuid'] ?? null;
                    if (!$accountUuid) continue;
                    
                    try {
                        $domains = $this->client->getAccountDomains($accountUuid);
                        // Inject account_uuid into domain for batch processing context
                        foreach ($domains as &$domain) {
                            $domain['account_uuid'] = $accountUuid;
                        }
                        $allDomains = array_merge($allDomains, $domains);
                    } catch (\Exception $e) {
                         \Log::warning('Sendmarc sync: Failed to fetch domains for account', ['account' => $accountUuid]);
                    }
                }
                
                $totalDomains = count($allDomains);
                
                if ($resumeOffset > 0) {
                     $allDomains = array_slice($allDomains, $resumeOffset);
                     $totalProcessed = $resumeOffset;
                }
                
                \Log::info('Sendmarc sync: Processing domains synchronously', [
                    'count' => count($allDomains),
                    'total' => $totalDomains,
                    'offset' => $resumeOffset
                ]);

                // 1. Synchronous Domain Sync (Customer Creation)
                foreach ($allDomains as $domain) {
                     try {
                        // Create Customer (Phase 1 Logic)
                        $standardized = SendmarcMapper::mapDomain($domain);
                        $this->storeCustomer($standardized);
                        
                        $totalProcessed++;
                     } catch (\Exception $e) {
                         \Log::warning('Sendmarc sync: Domain store failed', [
                             'domain' => $domain['domain_name'] ?? 'unknown',
                             'error' => $e->getMessage()
                         ]);
                     }
                }
                
                // 2. Immediate Automap
                $this->processUnmappedCustomers();

                // 3. Dispatch Metric Batches (Phase 2 Logic) - NOW we can do async/batch for data
                collect($allDomains)->chunk(10)->each(function ($chunk) {
                     \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'domains', $chunk->toArray(), $this->currentSyncLogId);
                });
                
                $this->saveSyncState('domains', $totalProcessed, $totalDomains);
            }
            
            // Completion
            $this->integration->update(['sync_state' => null]);
            $this->updateStatsCache();
            $this->processUnmappedCustomers();

        } catch (\Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }
            $errors[] = "Sync failed: {$e->getMessage()}";
             \Log::error('Sendmarc sync: Failed', ['error' => $e->getMessage()]);
        }

        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0, // Calculated by batches
            'customers_mapped' => [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }
    
    private function updateStatsCache(): void
    {
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
    }

    /**
     * @return array<int, mixed>
     */
    private function processUnmappedCustomers(): array
    {
        // Only dispatch if there are actually unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            \Log::info('Sendmarc: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return []; // Batch job handles mapping asynchronously
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // NO MORE $data = [] bucket! Just track stats
        $errors = [];
        $totalProcessed = 0;

        try {
            $accounts = $this->client->getAccounts();

            // PHASE 1: Create all customers FIRST (like BitDefender)
            // This ensures IntegrationCustomer records exist before we try to store data
            \Log::info('Sendmarc historical: Creating customers first', [
                'integration_id' => $this->integration->integration_id,
                'account_count' => count($accounts),
            ]);

            foreach ($accounts as $account) {
                $accountUuid = $account['uuid'] ?? null;
                if (!$accountUuid) continue;

                $domains = $this->client->getAccountDomains($accountUuid);
                
                foreach ($domains as $index => $domain) {
                    if ($index % 10 === 0) $this->sendHeartbeat();
                    try {
                        $standardized = SendmarcMapper::mapDomain($domain);
                        $this->storeCustomer($standardized);
                    } catch (\Exception $e) {
                        $domainName = $domain['domain_name'] ?? 'unknown';
                        \Log::warning('Sendmarc historical: Failed to create customer', [
                            'domain' => $domainName,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            \Log::info('Sendmarc historical: Customers created, now fetching data', [
                'integration_id' => $this->integration->integration_id,
            ]);

            // PHASE 2: Now fetch historical data (customers guaranteed to exist)
            foreach ($accounts as $account) {
                $accountUuid = $account['uuid'] ?? null;
                if (!$accountUuid) continue;

                $domains = $this->client->getAccountDomains($accountUuid);

                collect($domains)->chunk(50)->each(function ($chunk) use ($accountUuid, &$errors, &$totalProcessed, $startDate, $endDate) {
                    foreach ($chunk as $index => $domain) {
                        if ($index % 10 === 0) $this->sendHeartbeat();
                        $domainUuid = $domain['uuid'] ?? null;
                        if (!$domainUuid) continue;

                        try {
                            // 1. Fetch senders for the specific date range
                            $senders = $this->client->getDomainSenders($accountUuid, $domainUuid, $startDate, $endDate);
                            
                            foreach ($senders as $sender) {
                                // Important: Sendmarc senders provide a timeline. We should store each timeline entry.
                                if (!empty($sender['timeline']) && is_array($sender['timeline'])) {
                                    foreach ($sender['timeline'] as $timelineEntry) {
                                        $timestamp = isset($timelineEntry['date']) ? Carbon::parse($timelineEntry['date']) : $startDate;
                                        
                                        // Only process if it falls within our current chunk (though API should have filtered it)
                                        if (!$timestamp->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())) {
                                            continue;
                                        }

                                        // Merge timeline stats onto sender object for mapping
                                        $senderSnapshot = array_merge($sender, $timelineEntry);
                                        $standardized = SendmarcMapper::mapSenderStatistics($senderSnapshot, $domainUuid, $timestamp);
                                        $this->storeHistoricalData($standardized);
                                        $totalProcessed++;
                                    }
                                } else {
                                    // Fallback for senders without timeline (use startDate as fallback timestamp)
                                    $standardized = SendmarcMapper::mapSenderStatistics($sender, $domainUuid, $startDate);
                                    $this->storeHistoricalData($standardized);
                                    $totalProcessed++;
                                }
                            }

                            // 2. Volume totals (using specific range)
                            $volumeTotals = $this->client->getDomainVolumeTotals($accountUuid, $domainUuid, $startDate, $endDate);
                            if (!empty($volumeTotals)) {
                                $standardized = SendmarcMapper::mapVolumeTotals($volumeTotals, $domainUuid, $startDate);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            }

                            // 3. Volume timeline
                            // Sendmarc timeline API is unreliable because it skips days with zero data and doesn't provide dates.
                            // We fetch day-by-day using the Totals API to ensure 100% accurate date mapping.
                            $currentDate = $startDate->copy()->startOfDay();
                            $lastSyncDate = $endDate->copy()->startOfDay();

                            while ($currentDate <= $lastSyncDate) {
                                $this->sendHeartbeat();
                                try {
                                    $dailyTotal = $this->client->getDomainVolumeTotals($accountUuid, $domainUuid, $currentDate, $currentDate);
                                    if (!empty($dailyTotal)) {
                                        $standardized = SendmarcMapper::mapVolumeTimeline($dailyTotal, $domainUuid, 0, $currentDate->copy()->startOfDay());
                                        $this->storeHistoricalData($standardized);
                                        $totalProcessed++;
                                    }
                                    // Add small delay to avoid hitting 60/min rate limit too hard
                                    usleep(1500000); 
                                } catch (\Exception $e) {
                                    \Log::warning('Sendmarc historical: Failed to fetch daily total', [
                                        'domain' => $domain['domain_name'] ?? 'unknown',
                                        'date' => $currentDate->toDateString(),
                                        'error' => $e->getMessage()
                                    ]);
                                }
                                $currentDate->addDay();
                            }

                            // 4. IP addresses
                            $ipAddresses = $this->client->getDomainIpAddresses($accountUuid, $domainUuid, $startDate, $endDate);
                            foreach ($ipAddresses as $ipData) {
                                $standardized = SendmarcMapper::mapIpAddressStatistics($ipData, $domainUuid, $startDate);
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

        if (!$integrationCustomer->customer_id) {
            if ($this->integration->is_source_of_truth) {
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Sendmarc');
            } else {
                // Batch job processes all unmapped customers at end of sync (in processUnmappedCustomers)
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
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

        // UPSERT: Use domain_uuid + data_type + date as unique key
        $dateOnly = Carbon::parse($data['timestamp'])->startOfDay();
        
        $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
            ->where('data_type', $data['data_type'])
            ->where('integration_customer_id', $integrationCustomer->integration_customer_id)
            ->whereDate('data_timestamp', $dateOnly)
            ->first();

        if ($existing) {
            $existing->update([
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $data['payload'],
                'imported_at' => now(),
            ]);
        } else {
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

    private function fetchAndStoreCurrentMetrics(string $accountUuid, string $domainUuid): void
    {
        // 1. Fetch and store volume totals
        try {
            $volumeTotals = $this->client->getDomainVolumeTotals($accountUuid, $domainUuid);
            if (!empty($volumeTotals)) {
                $volumeData = SendmarcMapper::mapVolumeTotals($volumeTotals, $domainUuid);
                $this->storeCurrentMetrics($volumeData);
            }
        } catch (\Exception $e) {
            \Log::warning('Sendmarc sync: Failed to fetch volume totals', [
                'domain_uuid' => $domainUuid,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Fetch sender count (lightweight - just count, not details)
        try {
            $senders = $this->client->getDomainSenders($accountUuid, $domainUuid);
            $senderCount = count($senders);
            
            // Store sender summary (not individual senders)
            $senderSummary = [
                'data_type' => 'sender_summary',
                'timestamp' => now(),
                'payload' => [
                    'domain_uuid' => $domainUuid,
                    'total_senders' => $senderCount,
                    'fetched_at' => now()->toIso8601String(),
                ],
            ];
            $this->storeCurrentMetrics($senderSummary);
        } catch (\Exception $e) {
            \Log::warning('Sendmarc sync: Failed to fetch sender count', [
                'domain_uuid' => $domainUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeCurrentMetrics(array $data): void
    {
        $domainUuid = $data['payload']['domain_uuid'] ?? null;
        
        if (!$domainUuid) {
            return;
        }
        
        // Find integration customer by domain UUID
        $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereJsonContains('metadata->uuid', $domainUuid)
            ->first();

        if (!$integrationCustomer) {
            \Log::warning('Sendmarc sync: Integration customer not found for metrics', [
                'domain_uuid' => $domainUuid
            ]);
            return;
        }

        // UPSERT: Use domain_uuid + data_type + date as unique key (one metric per day)
        $dateOnly = Carbon::parse($data['timestamp'])->startOfDay();
        
        $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
            ->where('data_type', $data['data_type'])
            ->where('integration_customer_id', $integrationCustomer->integration_customer_id)
            ->whereDate('data_timestamp', $dateOnly)
            ->first();

        if ($existing) {
            $existing->update([
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $data['payload'],
                'imported_at' => now(),
            ]);
        } else {
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

    protected function saveSyncState(string $phase, int $processed, int $total): void
    {
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update([
                'sync_state' => json_encode([
                    'phase' => $phase,
                    'offset' => $processed,
                    'total' => $total,
                    'timestamp' => now()->timestamp,
                ])
            ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function processBatch(string $type, array $items): void
    {
        if ($type === 'domains') {
            foreach ($items as $domain) {
                // Heartbeat per item in batch
                $this->sendHeartbeat();
                
                try {
                    // 1. Create Customer (Phase 1 Logic)
                    $standardized = SendmarcMapper::mapDomain($domain);
                    $this->storeCustomer($standardized);

                    // 2. Fetch Metrics (Phase 2 Logic)
                    $accountUuid = $domain['account_uuid'] ?? null; // Ensure this is passed in item
                    $domainUuid = $domain['uuid'] ?? null;

                    if ($accountUuid && $domainUuid) {
                         $this->fetchAndStoreCurrentMetrics($accountUuid, $domainUuid);
                    }

                } catch (\Exception $e) {
                    \Log::error('Sendmarc batch: Failed to process domain', [
                        'domain' => $domain['domain_name'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
