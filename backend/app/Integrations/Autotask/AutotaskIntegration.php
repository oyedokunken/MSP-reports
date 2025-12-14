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
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutotaskIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    
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

        try {
            $autotaskCompanies = $this->client->getCompanies();
            
            \Log::info('Autotask sync: Retrieved companies', [
                'count' => count($autotaskCompanies),
                'integration_id' => $this->integration->integration_id,
            ]);

            $totalCompanies = count($autotaskCompanies);
            collect($autotaskCompanies)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, $totalCompanies) {
                foreach ($chunk as $company) {
                    try {
                        $standardized = AutotaskMapper::mapCompany($company);

                        $this->storeCustomer($standardized);
                        $totalProcessed++;

                    } catch (\Exception $e) {
                        $errors[] = "Failed to process company {$company['id']}: {$e->getMessage()}";
                        \Log::error('Autotask sync: Failed to process company', [
                            'integration_id' => $this->integration->integration_id,
                            'company_id' => $company['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                \Log::info('Autotask sync: Processed chunk', [
                    'integration_id' => $this->integration->integration_id,
                    'processed' => $totalProcessed,
                    'total' => $totalCompanies,
                ]);
            });

        } catch (\Exception $e) {
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

        \Log::info('Autotask sync: Completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $mappedCustomers,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        $unmappedCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->get();

        $mappedList = [];

        foreach ($unmappedCustomers as $integrationCustomer) {
            if ($this->integration->is_source_of_truth) {
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Autotask');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
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

            // Process tickets
            $ticketsProcessed = 0;
            $tickets = $this->client->getTickets($startDate, $endDate);
            
            // Build a ticket-to-company map for time entries
            $ticketToCompanyMap = [];
            foreach ($tickets as $ticket) {
                $ticketToCompanyMap[$ticket['id']] = $ticket['companyID'] ?? null;
            }
            
            // Process tickets in chunks
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
                            'data_payload' => json_encode($standardized['payload']),
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
            
            \Log::info('Autotask historical (OPTIMIZED): Tickets processed', [
                'integration_id' => $this->integration->integration_id,
                'tickets_processed' => $ticketsProcessed,
            ]);

            // Process time entries
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
                            'data_payload' => json_encode($standardized['payload']),
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
            
            \Log::info('Autotask historical (OPTIMIZED): Time entries processed', [
                'integration_id' => $this->integration->integration_id,
                'time_entries_processed' => $timeEntriesProcessed,
            ]);

            // Process resources
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
                                'data_payload' => json_encode($standardized['payload']),
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

            \Log::info('Autotask historical (OPTIMIZED): Completed', [
                'integration_id' => $this->integration->integration_id,
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            \Log::error('Autotask historical (OPTIMIZED): Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    private function storeHistoricalDataBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $dataType = $records[0]['data_type'];
        \Log::info("Processing batch of " . count($records) . " {$dataType} records");

        // Determine the ID field based on data type
        $idField = match ($dataType) {
            'ticket' => 'ticket_id',
            'time_entry' => 'time_entry_id',
            'resource' => 'resource_id',
            'contract' => 'contract_id',
            default => 'id',
        };

        // Extract external IDs from the new records
        $newIds = [];
        foreach ($records as $record) {
            $payload = is_string($record['data_payload']) ? json_decode($record['data_payload'], true) : $record['data_payload'];
            if (isset($payload[$idField])) {
                $newIds[] = $payload[$idField];
            } elseif (isset($payload['id'])) {
                // Fallback to 'id' if specific field not found
                $newIds[] = $payload['id'];
            }
        }

        if (empty($newIds)) {
            \Log::warning("No IDs found in batch for type {$dataType} (looking for {$idField})");
            // Fallback to insert all if no IDs found
            DB::table('integration_data')->insert($records);
            return;
        }

        // Find existing records with these IDs
        // Use raw SQL for JSON extraction to be efficient
        $placeholders = implode(',', array_fill(0, count($newIds), '?'));
        
        // Query existing records with timestamp for composite key matching
        $existingRecords = DB::select("
            SELECT integration_data_id, data_timestamp, data_payload->>'$idField' as external_id
            FROM integration_data 
            WHERE integration_id = ? 
            AND data_type = ? 
            AND data_payload->>'$idField' IN ($placeholders)
        ", array_merge([$this->integration->integration_id, $dataType], $newIds));

        $existingMap = [];
        foreach ($existingRecords as $rec) {
            $existingMap[$rec->external_id] = [
                'id' => $rec->integration_data_id,
                'timestamp' => $rec->data_timestamp
            ];
        }

        \Log::info("Found " . count($existingMap) . " existing records out of " . count($newIds));

        $toInsert = [];
        $toUpdate = [];

        foreach ($records as $record) {
            $payload = is_string($record['data_payload']) ? json_decode($record['data_payload'], true) : $record['data_payload'];
            $externalId = $payload[$idField] ?? $payload['id'] ?? null;

            if ($externalId && isset($existingMap[$externalId])) {
                $record['integration_data_id'] = $existingMap[$externalId]['id'];
                $record['data_timestamp'] = $existingMap[$externalId]['timestamp']; 
                $toUpdate[] = $record;
            } else {
                $toInsert[] = $record;
            }
        }

        \Log::info("Inserting " . count($toInsert) . ", Updating " . count($toUpdate));

        if (!empty($toInsert)) {
            DB::table('integration_data')->insert($toInsert);
        }

        if (!empty($toUpdate)) {
            DB::table('integration_data')->upsert(
                $toUpdate,
                ['integration_data_id', 'data_timestamp'],
                ['data_payload', 'imported_at', 'integration_customer_id', 'data_version']
            );
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
        // Validate required fields
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

            if (!$integrationCustomer->customer_id) {
                if ($this->integration->is_source_of_truth) {
                    $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Autotask');
                } else {
                    AutoMatchCustomersJob::dispatch($integrationCustomer);
                }
            }
        });
    }

    private function storeHistoricalData(array $data): void
    {
        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $data['integration_customer_id'],
            'data_type' => $data['data_type'],
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $data['payload'],
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }
}
