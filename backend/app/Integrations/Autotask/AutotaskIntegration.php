<?php

declare(strict_types=1);

namespace App\Integrations\Autotask;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Integrations\Contracts\IntegrationDataType;

final class AutotaskIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    
    protected Integration $integration;
    protected AutotaskClient $client;
    protected CustomerMappingService $mappingService;
    protected int $lastBatchProcessedCount = 0;




    private const BATCH_SIZE_TICKETS = 50;
    private const BATCH_SIZE_TIME_ENTRIES = 50;
    private const CACHE_TTL_MINUTES = 10;
    private const SYNC_LOOKBACK_MINUTES = 5;
    private const SYNC_DEFAULT_LOOKBACK_MINUTES = 60;
    private const HISTORICAL_BATCH_SIZE = 1000;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new AutotaskClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /** @var array<string, mixed> */
    protected $mappedDuringSync = [];

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;
        $batchesDispatched = 0;

        // Load Checkpoint
        $syncState = json_decode($this->integration->sync_state ?? '{}', true);
        $resumePhase = $syncState['phase'] ?? null;
        $resumeOffset = $syncState['offset'] ?? 0;

        if ($resumePhase) {
            \Log::info("Resuming sync from checkpoint", ['phase' => $resumePhase, 'offset' => $resumeOffset]);
        }

        try {
            // Determine sync starting point
            // For first sync (no last_sync_at), we want all companies ($since = null)
            // For subsequent syncs, we only want modified companies
            $since = $this->integration->last_sync_at 
                ? Carbon::parse($this->integration->last_sync_at)->subHours(1) // subHour for safety overlap
                : null;
                
            $autotaskCompanies = [];
            
            // Skip company fetch if we're already past this phase
            if (!in_array($resumePhase, [IntegrationDataType::TICKET, IntegrationDataType::TIME_ENTRY])) {
                $autotaskCompanies = $this->client->getCompanies($since);
                
                // Apply offset if resuming in companies phase
                if ($resumePhase === 'companies' && $resumeOffset > 0) {
                    \Log::info("Skipping first $resumeOffset companies");
                    $autotaskCompanies = array_slice($autotaskCompanies, $resumeOffset);
                    $totalProcessed = $resumeOffset;
                }
            }
            
            \Log::info('Autotask sync: Retrieved companies', [
                'count' => count($autotaskCompanies),
                'integration_id' => $this->integration->integration_id,
                'incremental' => !is_null($since),
                'since' => $since ? $since->toIso8601String() : 'beginning',
            ]);

            $totalCompanies = count($autotaskCompanies);
            
            Log::info('Autotask sync: Processing companies synchronously', [
                'count' => $totalCompanies
            ]);

            // 1. Synchronous Company Sync
            $companiesBatch = [];
            foreach ($autotaskCompanies as $company) {
                try {
                    $companiesBatch[] = AutotaskMapper::mapCompany($company);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    Log::warning('Autotask Sync: Company map failed', [
                        'id' => $company['id'] ?? 'unknown', 
                        'error' => $e->getMessage()
                    ]);
                }
            }
            $this->bulkStoreCustomers($companiesBatch);
            
            // 2. Immediate Automap
            $this->processUnmappedCustomers();

            $this->saveSyncState('companies', $totalProcessed, $totalCompanies);
            
            // Save state if we just finished companies
            if (!empty($autotaskCompanies)) {
                 $this->saveSyncState(IntegrationDataType::TICKET, 0, 0); // Ready for tickets
            }


        } catch (\Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }

            $errors[] = "Sync failed: {$e->getMessage()}";
            \Log::error('Autotask sync: Failed', [
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
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        $ticketsProcessed = 0;
        $timeEntriesProcessed = 0;
        
        try {
            $since = $this->integration->last_sync_at ? Carbon::parse($this->integration->last_sync_at)->subMinutes(self::SYNC_LOOKBACK_MINUTES) : now()->subMinutes(self::SYNC_DEFAULT_LOOKBACK_MINUTES);
            $until = now();
            
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
            
            $recentTickets = $this->client->getTickets($since, $until);
            
            // Resume logic for tickets
            if ($resumePhase === IntegrationDataType::TICKET && $resumeOffset > 0) {
                $recentTickets = array_slice($recentTickets, $resumeOffset);
                $ticketsProcessed = $resumeOffset;
            } elseif ($resumePhase === IntegrationDataType::TIME_ENTRY) {
                $recentTickets = []; // Skip tickets if already past
            }
            
            $ticketToCompanyMap = [];
            
            if ($ticketsProcessed % self::BATCH_SIZE_TICKETS === 0) $this->sendHeartbeat();
            
            // Chunk manually since array_slice might have messed up keys/indexing
            $ticketChunks = array_chunk($recentTickets, self::BATCH_SIZE_TICKETS);
            
            foreach ($ticketChunks as $chunk) {
                 \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, IntegrationDataType::TICKET, $chunk, $this->currentSyncLogId);
                 $ticketsProcessed += count($chunk);
                 $batchesDispatched++;
                 $this->saveSyncState(IntegrationDataType::TICKET, $ticketsProcessed, count($recentTickets));
            }
            
            $recentTimeEntries = $this->client->getTimeEntries($since, $until);
            
             // Resume logic for time entries
            if ($resumePhase === IntegrationDataType::TIME_ENTRY && $resumeOffset > 0) {
                $recentTimeEntries = array_slice($recentTimeEntries, $resumeOffset);
                $timeEntriesProcessed = $resumeOffset;
            }
            
            if ($timeEntriesProcessed % self::BATCH_SIZE_TIME_ENTRIES === 0) $this->sendHeartbeat();
            
            $timeChunks = array_chunk($recentTimeEntries, self::BATCH_SIZE_TIME_ENTRIES);
             foreach ($timeChunks as $chunk) {
                 \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, IntegrationDataType::TIME_ENTRY, $chunk, $this->currentSyncLogId);
                 $timeEntriesProcessed += count($chunk);
                 $batchesDispatched++;
                 $this->saveSyncState(IntegrationDataType::TIME_ENTRY, $timeEntriesProcessed, count($recentTimeEntries));
            }
            
        } catch (\Exception $e) {
            \Log::warning('Autotask: Ticket/time polling failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        \Log::info('Autotask sync: Completed', [
            'integration_id' => $this->integration->integration_id,
            'companies_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'tickets_synced' => $ticketsProcessed,
            'time_entries_synced' => $timeEntriesProcessed,
            'errors' => count($errors),
        ]);

        // Clear checkpoint on success
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update(['sync_state' => null]);

        return [
            'records_synced' => $mappedCount + $unmappedCount + $ticketsProcessed + $timeEntriesProcessed,
            'records_new' => $unmappedCount,
            'customers_mapped' => $mappedCustomers,
            'records_failed' => count($errors),
            'errors' => $errors,
            'batches_dispatched' => $batchesDispatched,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function processUnmappedCustomers(): array
    {
        // For source of truth integrations, customers are mapped inline in storeCustomer()
        // Skip the async job to avoid redundant processing
        // For source of truth integrations, customers are usually mapped inline in storeCustomer()
        // However, we allow the batch job to run as a safety net/self-healing mechanism
        // in case any customers were missed or created manually.

        // Dispatch batch job ONLY if there are unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            \Log::info('Autotask: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        } else {
            \Log::info('Autotask: Skipped auto-match - all customers mapped', [
                'integration_id' => $this->integration->integration_id,
            ]);
        }
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $autotaskCompanies = $this->client->getCompanies();
        return array_map(fn($company) => AutotaskMapper::mapCompany($company), $autotaskCompanies);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $errors = [];
        $totalProcessed = 0;

        try {
            \Log::info('Autotask historical: Starting', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Pre-load customer map
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();

            $ticketsProcessed = 0;
            $tickets = $this->client->getTickets($startDate, $endDate);
            
            $ticketToCompanyMap = [];
            foreach ($tickets as $ticket) {
                $ticketToCompanyMap[$ticket['id']] = $ticket['companyID'] ?? null;
            }
            
            foreach (array_chunk($tickets, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                $batchRecords = [];

                foreach ($chunk as $ticket) {
                    try {
                        $companyId = $ticket['companyID'] ?? null;
                        $integrationCustomerId = null;
                        
                        if ($companyId) {
                            $integrationCustomerId = $customerMap[(string)$companyId] ?? null;
                        }
                        
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        
                        $batchRecords[] = [
                            'integration_customer_id' => $integrationCustomerId,
                            'external_id' => (string)$ticket['id'],
                            'timestamp' => $standardized['timestamp'],
                            'payload' => $standardized['payload'],
                        ];
                        
                        $ticketsProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process ticket: {$e->getMessage()}";
                    }
                }

                if (!empty($batchRecords)) {
                    $this->bulkStoreHistoricalData(IntegrationDataType::TICKET, $batchRecords);
                }

                
                unset($chunk, $batchRecords);
            }
            
            $totalProcessed += $ticketsProcessed;
            
            unset($tickets);
            gc_collect_cycles();
            
            \Log::info('Autotask historical: Tickets processed', [
                'integration_id' => $this->integration->integration_id,
                'tickets_processed' => $ticketsProcessed,
            ]);

            $timeEntriesProcessed = 0;
            $timeEntries = $this->client->getTimeEntries($startDate, $endDate);
            
            foreach (array_chunk($timeEntries, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                $batchRecords = [];

                foreach ($chunk as $entry) {
                    try {
                        $ticketId = $entry['ticketID'] ?? null;
                        $integrationCustomerId = null;
                        
                        if ($ticketId && isset($ticketToCompanyMap[$ticketId])) {
                            $companyId = $ticketToCompanyMap[$ticketId];
                            if ($companyId) {
                                $integrationCustomerId = $customerMap[(string)$companyId] ?? null;
                            }
                        }
                        
                        $standardized = AutotaskMapper::mapTimeEntry($entry);
                        
                        $batchRecords[] = [
                            'integration_customer_id' => $integrationCustomerId,
                            'external_id' => (string)$entry['id'],
                            'timestamp' => $standardized['timestamp'],
                            'payload' => $standardized['payload'],
                        ];

                        $timeEntriesProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process time entry: {$e->getMessage()}";
                    }
                }

                if (!empty($batchRecords)) {
                    $this->bulkStoreHistoricalData(IntegrationDataType::TIME_ENTRY, $batchRecords);
                }
                
                unset($chunk, $batchRecords);
            }
            
            $totalProcessed += $timeEntriesProcessed;
            
            unset($timeEntries, $ticketToCompanyMap);
            gc_collect_cycles();
            
            \Log::info('Autotask historical: Time entries processed', [
                'integration_id' => $this->integration->integration_id,
                'time_entries_processed' => $timeEntriesProcessed,
            ]);

            try {
                $resources = $this->client->getResources();
                $resourcesProcessed = 0;
                
                foreach (array_chunk($resources, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                    $batchRecords = [];
                    
                    foreach ($chunk as $resource) {
                        try {
                            $standardized = AutotaskMapper::mapResource($resource);
                            
                            $batchRecords[] = [
                                'integration_customer_id' => null,
                                'external_id' => (string)$resource['id'],
                                'timestamp' => $standardized['timestamp'],
                                'payload' => $standardized['payload'],
                            ];

                            $resourcesProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process resource: {$e->getMessage()}";
                        }
                    }

                if (!empty($batchRecords)) {
                    $this->bulkStoreHistoricalData(IntegrationDataType::RESOURCE, $batchRecords);
                }
                    
                    unset($chunk, $batchRecords);
                }
                
                $totalProcessed += $resourcesProcessed;
                unset($resources);
                gc_collect_cycles();

            } catch (\Exception $e) {
                $errors[] = "Failed to fetch resources: {$e->getMessage()}";
            }

            try {
                $contracts = $this->client->getContracts(); 
                $contractsProcessed = 0;

                foreach (array_chunk($contracts, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                    $batchRecords = [];

                    foreach ($chunk as $contract) {
                        try {
                            $standardized = AutotaskMapper::mapContract($contract);
                            
                            $batchRecords[] = [
                                'integration_customer_id' => null,
                                'external_id' => (string)$contract['id'],
                                'timestamp' => $standardized['timestamp'],
                                'payload' => $standardized['payload'],
                            ];
                            $contractsProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process contract: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) {
                        $this->bulkStoreHistoricalData(IntegrationDataType::CONTRACT, $batchRecords);
                    }
                }
                $totalProcessed += $contractsProcessed;
                 \Log::info('Autotask historical: Contracts processed', [
                    'count' => $contractsProcessed
                ]);
            } catch (\Exception $e) {
                 $errors[] = "Failed to fetch contracts: {$e->getMessage()}";
            }

             try {
                $contacts = $this->client->getContacts($startDate, $endDate);
                $contactsProcessed = 0;

                foreach (array_chunk($contacts, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                    $batchRecords = [];
                    foreach ($chunk as $contact) {
                        try {
                            $companyId = $contact['companyID'] ?? null;
                            $integrationCustomerId = $companyId ? ($customerMap[(string)$companyId] ?? null) : null;
                            
                            $standardized = AutotaskMapper::mapContact($contact);
                            
                            $batchRecords[] = [
                                'integration_customer_id' => $integrationCustomerId,
                                'external_id' => (string)$contact['id'],
                                'timestamp' => $standardized['timestamp'],
                                'payload' => $standardized['payload'],
                            ];
                            $contactsProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process contact: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) {
                        $this->bulkStoreHistoricalData(IntegrationDataType::CONTACT, $batchRecords);
                    }
                }
                $totalProcessed += $contactsProcessed;
                 \Log::info('Autotask historical: Contacts processed', [
                    'count' => $contactsProcessed
                ]);
            } catch (\Exception $e) {
                 $errors[] = "Failed to fetch contacts: {$e->getMessage()}";
            }

            try {
                $notes = $this->client->getTicketNotes($startDate, $endDate);
                $notesProcessed = 0;

                foreach (array_chunk($notes, self::HISTORICAL_BATCH_SIZE) as $chunk) {
                    $batchRecords = [];
                    foreach ($chunk as $note) {
                        try {
                            $standardized = AutotaskMapper::mapTicketNote($note);

                            $batchRecords[] = [
                                'integration_customer_id' => null,
                                'external_id' => (string)$note['id'],
                                'timestamp' => $standardized['timestamp'],
                                'payload' => $standardized['payload'],
                            ];
                            $notesProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process note: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) {
                        $this->bulkStoreHistoricalData(IntegrationDataType::TICKET_NOTE, $batchRecords);
                    }
                }
                $totalProcessed += $notesProcessed;
                \Log::info('Autotask historical (OPTIMIZED): Notes processed', [
                    'count' => $notesProcessed
                ]);
            } catch (\Exception $e) {
                $errors[] = "Failed to fetch ticket notes: {$e->getMessage()}";
            }

            \Log::info('Autotask historical: Completed', [
                'integration_id' => $this->integration->integration_id,
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            \Log::error('Autotask historical: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    public function storeHistoricalDataBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // Detect data type from the first record if possible
        // Note: The loop below is a fallback for data passed in the old format.
        // New code should use bulkStoreHistoricalData directly.
        $this->bulkStoreHistoricalData($records[0]['data_type'] ?? 'unknown', $records);
    }

    public function getAvailableHistoryRange(): Carbon
    {
        return now()->subDays(365);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Autotask',
            'description' => 'Autotask PSA - Professional Services Automation',
            'data_types' => [
                IntegrationDataType::TICKET,
                IntegrationDataType::TIME_ENTRY,
                IntegrationDataType::CONTRACT,
                'billing',
                'service_calls',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        // Clean up any cached data or sessions
        // Autotask uses API key auth, so no session to destroy
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
            'requests_per_minute' => 100,
            'requests_per_hour' => 5000,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'your-email@company.com',
                'help' => 'Your Autotask API username (usually an email)',
            ],
            [
                'name' => 'secret',
                'label' => 'API Secret',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your API secret',
                'help' => 'Generated from Autotask Admin > Resources > API Users',
            ],
            [
                'name' => 'api_integration_code',
                'label' => 'API Integration Code',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'XXXXXXXXXXXXXXXXXX',
                'help' => 'Your unique integration tracking identifier',
            ],
            [
                'name' => 'zone',
                'label' => 'API Zone',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'https://webservices1.autotask.net/atservicesrest/v1.0' => 'Zone 1',
                    'https://webservices2.autotask.net/atservicesrest/v1.0' => 'Zone 2',
                    'https://webservices3.autotask.net/atservicesrest/v1.0' => 'Zone 3',
                    'https://webservices4.autotask.net/atservicesrest/v1.0' => 'Zone 4',
                    'https://webservices5.autotask.net/atservicesrest/v1.0' => 'Zone 5',
                    'https://webservices6.autotask.net/atservicesrest/v1.0' => 'Zone 6',
                    'https://webservices11.autotask.net/atservicesrest/v1.0' => 'Zone 11',
                    'https://webservices12.autotask.net/atservicesrest/v1.0' => 'Zone 12',
                    'https://webservices13.autotask.net/atservicesrest/v1.0' => 'Zone 13',
                    'https://webservices14.autotask.net/atservicesrest/v1.0' => 'Zone 14',
                    'https://webservices15.autotask.net/atservicesrest/v1.0' => 'Zone 15',
                    'https://webservices16.autotask.net/atservicesrest/v1.0' => 'Zone 16',
                    'https://webservices17.autotask.net/atservicesrest/v1.0' => 'Zone 17',
                    'https://webservices18.autotask.net/atservicesrest/v1.0' => 'Zone 18',
                    'https://webservices19.autotask.net/atservicesrest/v1.0' => 'Zone 19',
                    'https://webservices20.autotask.net/atservicesrest/v1.0' => 'Zone 20',
                    'https://webservices21.autotask.net/atservicesrest/v1.0' => 'Zone 21',
                    'https://webservices22.autotask.net/atservicesrest/v1.0' => 'Zone 22',
                    'https://webservices23.autotask.net/atservicesrest/v1.0' => 'Zone 23',
                    'https://webservices24.autotask.net/atservicesrest/v1.0' => 'Zone 24',
                    'https://webservices25.autotask.net/atservicesrest/v1.0' => 'Zone 25',
                    'https://webservices26.autotask.net/atservicesrest/v1.0' => 'Zone 26',
                ],
                'default' => 'https://webservices26.autotask.net/atservicesrest/v1.0',
                'help' => 'Select your Autotask zone (check your Autotask login URL)',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function storeCustomer(array $customer): void
    {
        if (empty($customer['external_id']) || empty($customer['name'])) {
            $validator = app(\App\Services\IntegrationDataValidator::class);
            Log::warning('Autotask: Skipping customer with missing required fields', [
                'customer' => $validator->sanitizeForLogging($customer)
            ]);
            return;
        }

        DB::transaction(function () use ($customer) {
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

            // For source of truth integrations, auto-create unified customer inline
            // This fixes the race condition where BatchAutoMatchCustomersJob runs
            // before all company batch jobs complete, leaving customers unmapped
            if ($this->integration->is_source_of_truth && !$integrationCustomer->customer_id) {
                $this->mappingService->createFromSourceOfTruth(
                    $integrationCustomer,
                    $this->integration->integration_type
                );
            }
        });
    }

    /**
     * @param array<int, array<string, mixed>> $customers
     */
    private function bulkStoreCustomers(array $customers): void
    {
        if (empty($customers)) return;

        $now = now();
        $insertData = [];

        foreach ($customers as $customer) {
            if (empty($customer['external_id']) || empty($customer['name'])) {
                 continue;
            }
            
            $insertData[] = [
                'integration_id' => $this->integration->integration_id,
                'external_id'    => $customer['external_id'],
                'name'           => $customer['name'],
                'metadata'       => json_encode($customer['metadata']),
                'active'         => $customer['active'],
                'created_at'     => $now,
                'updated_at'     => $now,
                'last_seen_at'   => $now,
            ];
        }

        IntegrationCustomer::upsert(
            $insertData,
            ['integration_id', 'external_id'],
            ['name', 'metadata', 'active', 'last_seen_at', 'updated_at']
        );
        
        // Auto-mapping logic for Source of Truth is trickier in bulk.
        // For now, we rely on the BatchAutoMatchCustomersJob which is dispatched independently.
        // If inline mapping is critical for functionality, it should be done here via a loop 
        // after upsert, or by fetching the upserted IDs.
        // Given the existing code relied on mappedDuringSync array or similar, we might need to adjust.
        // However, standard storeCustomer was just updating one by one.
    }



    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function bulkStoreHistoricalData(string $dataType, array $batch): void
    {
        if (empty($batch)) return;

        $externalIds = array_filter(array_map(fn($item) => $item['external_id'] ?? null, $batch));
        $now = now();

        DB::transaction(function () use ($dataType, $batch, $externalIds, $now) {
            // Delete existing
            if (!empty($externalIds)) {
                DB::table('integration_data')
                    ->where('integration_id', $this->integration->integration_id)
                    ->where('data_type', $dataType)
                    ->whereIn('external_id', $externalIds)
                    ->delete();
            }

            $insertData = array_map(function ($item) use ($dataType, $now) {
                return [
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $item['integration_customer_id'],
                    'external_id' => $item['external_id'],
                    'data_type' => $dataType,
                    'data_timestamp' => $item['timestamp'],
                    'data_payload' => json_encode($item['payload']),
                    'imported_at' => $now,
                    'data_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $batch);

            DB::table('integration_data')->insert($insertData);
        });
    }

    private function saveSyncState(string $phase, int $offset, int $total): void
    {
        // Direct DB update to avoid model events/overhead
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update([
                'sync_state' => json_encode([
                    'phase' => $phase,
                    'offset' => $offset,
                    'total' => $total,
                    'timestamp' => now()->timestamp,
                ])
            ]);
    }

    public function getLastBatchProcessedCount(): int
    {
        return $this->lastBatchProcessedCount;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function processBatch(string $type, array $items): void
    {
        $this->lastBatchProcessedCount = 0;
        $this->sendHeartbeat();

        switch ($type) {
            case 'companies':
                foreach ($items as $company) {
                    try {
                        $standardized = AutotaskMapper::mapCompany($company);
                        $this->storeCustomer($standardized);
                        $this->lastBatchProcessedCount++;
                    } catch (\Exception $e) {
                         Log::error('Autotask batch: Failed to process company', ['id' => $company['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    }
                }
                break;
                
            case 'tickets':
            case IntegrationDataType::TICKET:
                $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->pluck('integration_customer_id', 'external_id')
                    ->toArray();
                    
                $batch = [];
                foreach ($items as $ticket) {
                    try {
                        $companyId = $ticket['companyID'] ?? null;
                        $integrationCustomerId = $companyId ? ($customerMap[(string)$companyId] ?? null) : null;
                        
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        
                        $batch[] = [
                            'integration_customer_id' => $integrationCustomerId,
                            'external_id' => (string)$ticket['id'],
                            'timestamp' => $standardized['timestamp'],
                            'payload' => $standardized['payload']
                        ];
                        $this->lastBatchProcessedCount++;
                    } catch (\Exception $e) {
                        Log::error('Autotask batch: Failed to map ticket', ['id' => $ticket['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    }

                    if ($this->lastBatchProcessedCount % 10 === 0) $this->sendHeartbeat();
                }
                $this->bulkStoreHistoricalData(IntegrationDataType::TICKET, $batch);
                break;

            case 'time_entries':
            case IntegrationDataType::TIME_ENTRY:
                $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->pluck('integration_customer_id', 'external_id')
                    ->toArray();
                    
                $batch = [];
                foreach ($items as $timeEntry) {
                    try {
                        $companyId = $timeEntry['companyID'] ?? null;
                        $integrationCustomerId = $companyId ? ($customerMap[(string)$companyId] ?? null) : null;
                        
                        $standardized = AutotaskMapper::mapTimeEntry($timeEntry);
                        
                        $batch[] = [
                            'integration_customer_id' => $integrationCustomerId,
                            'external_id' => (string)$timeEntry['id'],
                            'timestamp' => $standardized['timestamp'],
                            'payload' => $standardized['payload']
                        ];
                        $this->lastBatchProcessedCount++;
                    } catch (\Exception $e) {
                        Log::error('Autotask batch: Failed to map time entry', ['id' => $timeEntry['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    }

                    if ($this->lastBatchProcessedCount % 10 === 0) $this->sendHeartbeat();
                }
                $this->bulkStoreHistoricalData(IntegrationDataType::TIME_ENTRY, $batch);
                break;
        }

        $this->sendHeartbeat();
    }
}
