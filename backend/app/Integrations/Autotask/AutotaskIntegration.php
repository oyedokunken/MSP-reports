<?php

namespace App\Integrations\Autotask;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
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

class AutotaskIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    
    protected Integration $integration;
    protected AutotaskClient $client;
    protected CustomerMappingService $mappingService;



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

    protected $mappedDuringSync = [];

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

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
            if (!in_array($resumePhase, ['tickets', 'time_entries'])) {
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
            foreach ($autotaskCompanies as $company) {
                try {
                    $standardized = AutotaskMapper::mapCompany($company);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    Log::warning('Autotask Sync: Company store failed', [
                        'id' => $company['id'] ?? 'unknown', 
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // 2. Immediate Automap
            $this->processUnmappedCustomers();

            $this->saveSyncState('companies', $totalProcessed, $totalCompanies);
            
            // Save state if we just finished companies
            if (!empty($autotaskCompanies)) {
                 $this->saveSyncState('tickets', 0, 0); // Ready for tickets
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
        ], now()->addMinutes(10));

        $ticketsProcessed = 0;
        $timeEntriesProcessed = 0;
        
        try {
            $since = $this->integration->last_sync_at ? Carbon::parse($this->integration->last_sync_at)->subMinutes(5) : now()->subMinutes(60);
            $until = now();
            
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
            
            $recentTickets = $this->client->getTickets($since, $until);
            
            // Resume logic for tickets
            if ($resumePhase === 'tickets' && $resumeOffset > 0) {
                $recentTickets = array_slice($recentTickets, $resumeOffset);
                $ticketsProcessed = $resumeOffset;
            } elseif ($resumePhase === 'time_entries') {
                $recentTickets = []; // Skip tickets if already past
            }
            
            $ticketToCompanyMap = [];
            
                if ($ticketsProcessed % 50 === 0) $this->sendHeartbeat();
            
            // Chunk manually since array_slice might have messed up keys/indexing
            $ticketChunks = array_chunk($recentTickets, 50);
            
            foreach ($ticketChunks as $chunk) {
                 \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'tickets', $chunk, $this->currentSyncLogId);
                 $ticketsProcessed += count($chunk);
                 $this->saveSyncState('tickets', $ticketsProcessed, count($recentTickets));
            }
            
            $recentTimeEntries = $this->client->getTimeEntries($since, $until);
            
             // Resume logic for time entries
            if ($resumePhase === 'time_entries' && $resumeOffset > 0) {
                $recentTimeEntries = array_slice($recentTimeEntries, $resumeOffset);
                $timeEntriesProcessed = $resumeOffset;
            }
            
            if ($timeEntriesProcessed % 50 === 0) $this->sendHeartbeat();
            
            $timeChunks = array_chunk($recentTimeEntries, 50);
             foreach ($timeChunks as $chunk) {
                 \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'time_entries', $chunk, $this->currentSyncLogId);
                 $timeEntriesProcessed += count($chunk);
                 $this->saveSyncState('time_entries', $timeEntriesProcessed, count($recentTimeEntries));
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
        ];
    }

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

    public function getCustomers(): array
    {
        $autotaskCompanies = $this->client->getCompanies();
        return array_map(fn($company) => AutotaskMapper::mapCompany($company), $autotaskCompanies);
    }

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
            
            foreach (array_chunk($tickets, 1000) as $chunk) {
                $batchRecords = [];

                foreach ($chunk as $ticket) {
                    try {
                        $companyId = $ticket['companyID'] ?? null;
                        $integrationCustomerId = null;
                        
                        if ($companyId) {
                            $integrationCustomerId = $customerMap[(string)$companyId] ?? null;
                        }
                        
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        $standardized['integration_customer_id'] = $integrationCustomerId;
                        
                        $batchRecords[] = [
                            'company_id' => $this->integration->company_id,
                            'integration_id' => $this->integration->integration_id,
                            'integration_customer_id' => $integrationCustomerId,
                            'data_type' => $standardized['data_type'],
                            'data_timestamp' => $standardized['timestamp'],
                            'data_payload' => $standardized['payload'],
                            'imported_at' => now(),
                            'data_version' => 1,
                        ];
                        
                        $ticketsProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process ticket: {$e->getMessage()}";
                    }
                }

                if (!empty($batchRecords)) {
                    $this->storeHistoricalDataBatch($batchRecords);
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
            
            foreach (array_chunk($timeEntries, 1000) as $chunk) {
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
                        $standardized['integration_customer_id'] = $integrationCustomerId;
                        
                        $batchRecords[] = [
                            'company_id' => $this->integration->company_id,
                            'integration_id' => $this->integration->integration_id,
                            'integration_customer_id' => $integrationCustomerId,
                            'data_type' => $standardized['data_type'],
                            'data_timestamp' => $standardized['timestamp'],
                            'data_payload' => $standardized['payload'],
                            'imported_at' => now(),
                            'data_version' => 1,
                        ];

                        $timeEntriesProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process time entry: {$e->getMessage()}";
                    }
                }

                if (!empty($batchRecords)) {
                    $this->storeHistoricalDataBatch($batchRecords);
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
                
                foreach (array_chunk($resources, 1000) as $chunk) {
                    $batchRecords = [];
                    
                    foreach ($chunk as $resource) {
                        try {
                            $standardized = AutotaskMapper::mapResource($resource);
                            
                            $batchRecords[] = [
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => null,
                                'data_type' => $standardized['data_type'],
                                'data_timestamp' => $standardized['timestamp'],
                                'data_payload' => $standardized['payload'],
                                'imported_at' => now(),
                                'data_version' => 1,
                            ];

                            $resourcesProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process resource: {$e->getMessage()}";
                        }
                    }

                    if (!empty($batchRecords)) {
                        $this->storeHistoricalDataBatch($batchRecords);
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

                foreach (array_chunk($contracts, 1000) as $chunk) {
                    $batchRecords = [];

                    foreach ($chunk as $contract) {
                        try {
                            $standardized = AutotaskMapper::mapContract($contract);
                            
                            $batchRecords[] = [
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => null, // Contracts are company-wide but we don't have direct mapping here easily
                                'data_type' => $standardized['data_type'],
                                'data_timestamp' => $standardized['timestamp'],
                                'data_payload' => $standardized['payload'],
                                'imported_at' => now(),
                                'data_version' => 1,
                            ];
                            $contractsProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process contract: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) $this->storeHistoricalDataBatch($batchRecords);
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

                foreach (array_chunk($contacts, 1000) as $chunk) {
                    $batchRecords = [];
                    foreach ($chunk as $contact) {
                        try {
                            $standardized = AutotaskMapper::mapContact($contact);
                             $integrationCustomerId = null;
                            if ($contact['companyID'] ?? false) {
                                 $integrationCustomerId = $customerMap[(string)$contact['companyID']] ?? null;
                            }
                            
                            $standardized['integration_customer_id'] = $integrationCustomerId;

                            $batchRecords[] = [
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $integrationCustomerId,
                                'data_type' => $standardized['data_type'],
                                'data_timestamp' => $standardized['timestamp'],
                                'data_payload' => $standardized['payload'],
                                'imported_at' => now(),
                                'data_version' => 1,
                            ];
                            $contactsProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process contact: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) $this->storeHistoricalDataBatch($batchRecords);
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

                foreach (array_chunk($notes, 1000) as $chunk) {
                    $batchRecords = [];
                    foreach ($chunk as $note) {
                        try {
                            $standardized = AutotaskMapper::mapTicketNote($note);

                            $batchRecords[] = [
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => null,
                                'data_type' => $standardized['data_type'],
                                'data_timestamp' => $standardized['timestamp'],
                                'data_payload' => $standardized['payload'],
                                'imported_at' => now(),
                                'data_version' => 1,
                            ];
                            $notesProcessed++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process note: {$e->getMessage()}";
                        }
                    }
                    if (!empty($batchRecords)) $this->storeHistoricalDataBatch($batchRecords);
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

    public function storeHistoricalDataBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        foreach (array_chunk($records, 500) as $chunk) {
            foreach ($chunk as $record) {
                $uniqueKeyField = $this->getUniqueKeyField($record['data_type']);
                
                if ($uniqueKeyField) {
                    $payload = is_string($record['data_payload']) 
                        ? json_decode($record['data_payload'], true) 
                        : $record['data_payload'];
                        
                    $uniqueKey = $payload[$uniqueKeyField] ?? null;
                    
                    if ($uniqueKey) {
                        $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                            ->where('data_type', $record['data_type'])
                            ->whereRaw("data_payload->>'$uniqueKeyField' = ?", [$uniqueKey])
                            ->first();

                        if ($existing) {
                            $existing->update([
                                'data_payload' => $payload,
                                'imported_at' => $record['imported_at'],
                            ]);
                            continue;
                        }
                    }
                }
                
                IntegrationData::create($record);
            }
        }
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'Autotask',
            'description' => 'Autotask PSA - Professional Services Automation',
            'data_types' => [
                'tickets',
                'time_entries',
                'contracts',
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

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 100,
            'requests_per_hour' => 5000,
        ];
    }

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

    private function storeCustomer(array $customer): void
    {
        if (empty($customer['external_id']) || empty($customer['name'])) {
            Log::warning('Autotask: Skipping customer with missing required fields', [
                'customer' => $customer
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

    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $uniqueKey = $this->getUniqueKeyFromPayload($data['data_type'], $payload);
        
        if ($uniqueKey) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $data['data_type'])
                ->whereRaw("data_payload->>'{$this->getUniqueKeyField($data['data_type'])}' = ?", [$uniqueKey])
                ->first();

            if ($existing) {
                $existing->update([
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
            } else {
                IntegrationData::create([
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $data['integration_customer_id'],
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
                'integration_customer_id' => $data['integration_customer_id'],
                'data_type' => $data['data_type'],
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $payload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
    }

    private function getUniqueKeyFromPayload(string $dataType, array $payload): ?string
    {
        $keyField = $this->getUniqueKeyField($dataType);
        return $keyField ? ($payload[$keyField] ?? null) : null;
    }

    private function getUniqueKeyField(string $dataType): ?string
    {
        return match($dataType) {
            'ticket' => 'ticket_id',
            'time_entry' => 'time_entry_id',
            'resource' => 'resource_id',
            'contract' => 'contract_id',
            'contact' => 'contact_id',
            'ticket_note' => 'note_id',
            default => null,
        };
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

    public function processBatch(string $type, array $items): void
    {
        switch ($type) {
            case 'companies':
                foreach ($items as $company) {
                    try {
                        $standardized = AutotaskMapper::mapCompany($company);
                        $this->storeCustomer($standardized);
                    } catch (\Exception $e) {
                         Log::error('Autotask batch: Failed to process company', ['id' => $company['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    }
                }
                break;
                
            case 'tickets':
                // We need customer map for tickets
                 $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->pluck('integration_customer_id', 'external_id')
                    ->toArray();
                    
                foreach ($items as $ticket) {
                    try {
                        $companyId = $ticket['companyID'] ?? null;
                        $integrationCustomerId = $companyId ? ($customerMap[(string)$companyId] ?? null) : null;
                        
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        $standardized['integration_customer_id'] = $integrationCustomerId;
                        
                        $this->storeHistoricalData($standardized);
                    } catch (\Exception $e) {
                        Log::error('Autotask batch: Failed to process ticket', ['error' => $e->getMessage()]);
                    }
                }
                break;
                
             case 'time_entries':
                // We assume we can't easily map back to company without ticket lookup, 
                // but for batch processing we might skip the complex ticket->company lookup for performance
                // OR we'd need to fetch ticket info. For now, let's map what we can.
                 $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->pluck('integration_customer_id', 'external_id')
                    ->toArray();
                    
                foreach ($items as $entry) {
                    try {
                        // Optimistic: if we have ticketID, maybe we can query DB? 
                        // For now, accept we might miss company mapping on time entries in batch mode 
                        // unless we query; this is a trade-off for speed.
                        $integrationCustomerId = null;
                        
                        $standardized = AutotaskMapper::mapTimeEntry($entry);
                        $standardized['integration_customer_id'] = $integrationCustomerId;
                        
                        $this->storeHistoricalData($standardized);
                    } catch (\Exception $e) {
                        Log::error('Autotask batch: Failed to process time entry', ['error' => $e->getMessage()]);
                    }
                }
                break;
        }
    }
}

