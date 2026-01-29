<?php

namespace App\Integrations\Autotask;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\RateLimitExceededException;

class AutotaskClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected string $apiIntegrationCode;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected bool $useMockData = false;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        $this->useMockData = str_starts_with($credentials['username'] ?? '', 'test_');
        
        $this->baseUrl = rtrim($credentials['endpoint'] ?? 'https://webservices26.autotask.net/atservicesrest/v1.0', '/');
        
        $this->apiIntegrationCode = $credentials['api_integration_code'] ?? '';
    }

    public function testConnection(): bool
    {
        $response = $this->makeRequest('POST', '/Companies/query', [
            'filter' => [
                ['field' => 'id', 'op' => 'gt', 'value' => 0]
            ],
            'MaxRecords' => 1
        ]);
        
        if (!$response->successful()) {
            $statusCode = $response->status();
            $body = $response->body();
            
            \Log::error('Autotask connection test failed', [
                'status' => $statusCode,
                'body' => $body
            ]);
            
            throw new AuthenticationException(
                'Autotask authentication failed',
                $statusCode,
                $body
            );
        }
        
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCompanies(?Carbon $since = null): array
    {
        if ($this->useMockData) {
            return $this->getMockCompanies();
        }

        $allCompanies = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId]
                ];

                if ($since) {
                    $filters[] = ['field' => 'lastActivityDate', 'op' => 'gte', 'value' => $since->copy()->utc()->format('Y-m-d\TH:i:s\Z')];
                }

                $response = $this->makeRequest('POST', '/Companies/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    \Log::warning('Autotask API getCompanies failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allCompanies = array_merge($allCompanies, $items);
                $lastId = end($items)['id'];

                \Log::info('Autotask API companies page retrieved', [
                    'page_count' => count($items),
                    'total_so_far' => count($allCompanies),
                    'last_id' => $lastId
                ]);
            }

            \Log::info('Autotask API companies retrieval complete', ['total_count' => count($allCompanies)]);
            return $allCompanies;

        } catch (\Exception $e) {
            \Log::error('Autotask API getCompanies exception', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch companies: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTickets(Carbon $startDate, Carbon $endDate): array
    {
        $allTickets = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'lastActivityDate', 'op' => 'gte', 'value' => $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'lastActivityDate', 'op' => 'lt', 'value' => $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/Tickets/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                // Filter items to ensure they're within the date range
                // API pagination by ID can return items outside the date filter
                $filteredItems = array_filter($items, function($item) use ($startDate, $endDate) {
                    $lastActivity = $item['lastActivityDate'] ?? null;
                    if (!$lastActivity) {
                        return false;
                    }
                    try {
                        $activityDate = \Carbon\Carbon::parse($lastActivity);
                        return $activityDate->gte($startDate) && $activityDate->lt($endDate);
                    } catch (\Exception $e) {
                        return false;
                    }
                });

                $allTickets = array_merge($allTickets, $filteredItems);
                $lastId = end($items)['id'];
            }

            return $allTickets;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch tickets: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTimeEntries(Carbon $startDate, Carbon $endDate): array
    {
        $allEntries = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'dateWorked', 'op' => 'gte', 'value' => $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'dateWorked', 'op' => 'lt', 'value' => $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/TimeEntries/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                // Filter items to ensure they're within the date range
                // API pagination by ID can return items outside the date filter
                $filteredItems = array_filter($items, function($item) use ($startDate, $endDate) {
                    $dateWorked = $item['dateWorked'] ?? null;
                    if (!$dateWorked) {
                        return false;
                    }
                    try {
                        $workedDate = \Carbon\Carbon::parse($dateWorked);
                        return $workedDate->gte($startDate) && $workedDate->lt($endDate);
                    } catch (\Exception $e) {
                        return false;
                    }
                });

                $allEntries = array_merge($allEntries, $filteredItems);
                $lastId = end($items)['id'];
            }

            return $allEntries;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch time entries: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getContracts(): array
    {
        $allContracts = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $response = $this->makeRequest('POST', '/Contracts/query', [
                    'filter' => [
                        ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                        ['field' => 'status', 'op' => 'eq', 'value' => 1] // Active contracts
                    ],
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allContracts = array_merge($allContracts, $items);
                $lastId = end($items)['id'];
            }

            return $allContracts;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch contracts: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getResources(): array
    {
        $allResources = [];
        $pageSize = 500;

        try {
            $filters = [
                ['field' => 'isActive', 'op' => 'eq', 'value' => true]
            ];

            $response = $this->makeRequest('POST', '/Resources/query', [
                'filter' => $filters,
                'MaxRecords' => $pageSize,
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                $allResources = array_merge($allResources, $items);
                
                \Log::info('Autotask: Fetched resources', [
                    'count' => count($items),
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Autotask: Failed to fetch resources', [
                'error' => $e->getMessage(),
            ]);
        }

        return $allResources;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getContacts(Carbon $startDate, Carbon $endDate): array
    {
        $allContacts = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'lastActivityDate', 'op' => 'gte', 'value' => $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'lastActivityDate', 'op' => 'lt', 'value' => $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/Contacts/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    \Log::warning('Autotask API getContacts failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allContacts = array_merge($allContacts, $items);
                $lastId = end($items)['id'];
            }

            return $allContacts;
        } catch (\Exception $e) {
            \Log::error('Autotask API getContacts exception', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch contacts: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTicketNotes(Carbon $startDate, Carbon $endDate): array
    {
        $allNotes = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 500;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'createDateTime', 'op' => 'gte', 'value' => $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'createDateTime', 'op' => 'lt', 'value' => $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/TicketNotes/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'id',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allNotes = array_merge($allNotes, $items);
                $lastId = end($items)['id'];
            }

            return $allNotes;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch ticket notes: {$e->getMessage()}");
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function makeRequest(string $method, string $endpoint, array $params = []): Response
    {
        if (!$this->useMockData) {
            if (!$this->rateLimiter->waitForSlot($this->integration, 180)) {
                throw new RateLimitExceededException('Rate limit exceeded for Autotask API');
            }
        }

        $headers = [
            'Username'           => $this->credentials['username'] ?? '',
            'Secret'             => $this->credentials['secret'] ?? '',
            'APIIntegrationcode' => $this->apiIntegrationCode,
            'Accept'             => 'application/json',
            'Content-Type'       => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        $loggableHeaders = $headers;
        $loggableHeaders['Secret'] = '***';

        \Log::debug('Autotask API request', [
            'integration_id' => $this->integration->integration_id,
            'method'         => $method,
            'url'            => $url,
            'headers'        => $loggableHeaders,
            'body'           => $params
        ]);

        // Retry logic with exponential backoff for network errors
        $maxRetries = 3;
        $retryDelay = 1; // Start with 1 second
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $request = Http::withHeaders($headers)
                    ->timeout(120)
                    ->retry(2, 1000);

                $response = match (strtoupper($method)) {
                    'GET'  => $request->get($url),
                    'POST' => $request->post($url, $params),
                    default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
                };

                \Log::debug('Autotask API response', [
                    'integration_id' => $this->integration->integration_id,
                    'status'         => $response->status(),
                    'body_preview'   => substr($response->body(), 0, 500),
                    'attempt'        => $attempt
                ]);

                if (!$this->useMockData) {
                    $this->rateLimiter->recordRequest($this->integration);
                }

                return $response;

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                
                \Log::warning('Autotask API connection error, retrying...', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                    'next_retry_in' => $retryDelay . 's'
                ]);

                // Don't sleep on last attempt
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff: 1s, 2s, 4s
                }
            }
        }

        // If we get here, all retries failed
        \Log::error('Autotask API request failed after all retries', [
            'method' => $method,
            'endpoint' => $endpoint,
            'retries' => $maxRetries,
            'last_error' => $lastException->getMessage()
        ]);

        throw $lastException;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMockCompanies(): array
    {
        \Log::info('Autotask Client: Using mock data mode');
        
        return [
            [
                'id' => 'AT-1001',
                'companyName' => 'ACME Corporation',
                'isActive' => 1,
                'city' => 'Sydney',
                'state' => 'NSW',
                'country' => 'Australia',
                'phone' => '+61 2 9999 0001',
            ],
            [
                'id' => 'AT-1002',
                'companyName' => 'Beta Solutions Pty Ltd',
                'isActive' => 1,
                'city' => 'Melbourne',
                'state' => 'VIC',
                'country' => 'Australia',
                'phone' => '+61 3 9999 0002',
            ],
            [
                'id' => 'AT-1003',
                'companyName' => 'Gamma Tech Group',
                'isActive' => 1,
                'city' => 'Brisbane',
                'state' => 'QLD',
                'country' => 'Australia',
                'phone' => '+61 7 9999 0003',
            ],
            [
                'id' => 'AT-1004',
                'companyName' => 'TechStart Innovations Pty Ltd',
                'isActive' => 1,
                'city' => 'Brisbane',
                'state' => 'QLD',
                'country' => 'Australia',
                'phone' => '+61 7 9999 0004',
            ],
            [
                'id' => 'AT-1005',
                'companyName' => 'Sigma Networks Australia',
                'isActive' => 1,
                'city' => 'Perth',
                'state' => 'WA',
                'country' => 'Australia',
                'phone' => '+61 8 9999 0005',
            ],
            [
                'id' => 'AT-1006',
                'companyName' => 'Quantum IT Solutions',
                'isActive' => 1,
                'city' => 'Adelaide',
                'state' => 'SA',
                'country' => 'Australia',
                'phone' => '+61 8 9999 0006',
            ],
        ];
    }
}
