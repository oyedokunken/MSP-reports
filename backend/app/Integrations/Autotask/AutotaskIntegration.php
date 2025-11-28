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
        $data = [];
        $errors = [];
        $totalProcessed = 0;

        try {
            $tickets = $this->client->getTickets($startDate, $endDate);

            collect($tickets)->chunk(50)->each(function ($chunk) use (&$data, &$errors, &$totalProcessed) {
                foreach ($chunk as $ticket) {
                    try {
                        // Find the IntegrationCustomer for this ticket's company
                        $companyId = $ticket['companyID'] ?? null;
                        $integrationCustomer = null;
                        
                        if ($companyId) {
                            $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                ->where('external_id', (string)$companyId)
                                ->first();
                        }
                        
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        $standardized['integration_customer_id'] = $integrationCustomer?->integration_customer_id;
                        
                        $data[] = $standardized;
                        $this->storeHistoricalData($standardized);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process ticket: {$e->getMessage()}";
                        \Log::error('Autotask historical: Failed to process ticket', [
                            'integration_id' => $this->integration->integration_id,
                            'ticket_id' => $ticket['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $timeEntries = $this->client->getTimeEntries($startDate, $endDate);
            
            collect($timeEntries)->chunk(50)->each(function ($chunk) use (&$data, &$errors, &$totalProcessed, $tickets) {
                foreach ($chunk as $entry) {
                    try {
                        // Find the IntegrationCustomer by looking up the ticket's company
                        $ticketId = $entry['ticketID'] ?? null;
                        $integrationCustomer = null;
                        
                        if ($ticketId) {
                            // Find the ticket to get its companyID
                            $ticket = collect($tickets)->firstWhere('id', $ticketId);
                            $companyId = $ticket['companyID'] ?? null;
                            
                            if ($companyId) {
                                $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                    ->where('external_id', (string)$companyId)
                                    ->first();
                            }
                        }
                        
                        $standardized = AutotaskMapper::mapTimeEntry($entry);
                        $standardized['integration_customer_id'] = $integrationCustomer?->integration_customer_id;
                        
                        $data[] = $standardized;
                        $this->storeHistoricalData($standardized);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process time entry: {$e->getMessage()}";
                        \Log::error('Autotask historical: Failed to process time entry', [
                            'integration_id' => $this->integration->integration_id,
                            'entry_id' => $entry['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            \Log::info('Autotask historical: Completed', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
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
