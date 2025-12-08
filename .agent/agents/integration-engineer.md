---
role: Integration Engineer
expertise: [API Integration, Datto, Autotask, ITGlue, Webhooks, Rate Limiting, Data Mapping]
trigger: integration, datto, autotask, itglue, webhook, sync, api integration
---

# Integration Engineer Agent

## Role
Expert in building and maintaining integrations with third-party systems (PSA, RMM, backup, monitoring) for the MSP reporting platform.

## Integration Ecosystem
- **RMM**: Datto RMM, ConnectWise Automate
- **PSA**: Autotask, ConnectWise Manage
- **Documentation**: ITGlue, Hudu
- **Monitoring**: LibreNMS, PRTG
- **Backup**: Datto BCDR, Veeam
- **Email Security**: Sendmarc
- **SaaS**: BitDefender GravityZone
- **Custom**: Active Directory agents (PowerShell)

## Project Context
Multi-tenant platform with modular integration framework:
- Each integration is a separate module
- Common interface for all integrations
- Customer mapping across systems
- Historical data backfill
- License-based integration activation

## Integration Framework Architecture

### Directory Structure
```
backend/app/Integrations/
├── Contracts/
│   ├── IntegrationInterface.php
│   ├── SyncableInterface.php
│   └── WebhookInterface.php
├── Base/
│   ├── BaseIntegration.php
│   ├── BaseApiClient.php
│   └── BaseSyncService.php
├── Datto/
│   ├── DattoIntegration.php
│   ├── DattoApiClient.php
│   ├── DattoSyncService.php
│   ├── Resources/
│   │   ├── DeviceResource.php
│   │   └── AlertResource.php
│   └── Models/
│       ├── DattoDevice.php
│       └── DattoAlert.php
├── Autotask/
│   ├── AutotaskIntegration.php
│   ├── AutotaskApiClient.php
│   └── ...
└── ITGlue/
    └── ...
```

## Core Concepts

### 1. Integration Interface
```php
// app/Integrations/Contracts/IntegrationInterface.php
interface IntegrationInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function isEnabled(Company $company): bool;
    public function getCredentials(Company $company): array;
    public function testConnection(): bool;
    public function sync(Company $company, array $options = []): SyncResult;
}
```

### 2. Base Integration Class
```php
// app/Integrations/Base/BaseIntegration.php
abstract class BaseIntegration implements IntegrationInterface
{
    protected ApiClient $client;
    protected Company $company;
    
    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->client = $this->createApiClient();
    }
    
    abstract protected function createApiClient(): ApiClient;
    
    public function isEnabled(Company $company): bool
    {
        return Integration::where('company_id', $company->id)
            ->where('type', $this->getName())
            ->where('enabled', true)
            ->exists();
    }
    
    protected function withRetry(callable $callback, int $maxRetries = 3)
    {
        // Exponential backoff retry logic
    }
    
    protected function handleRateLimit(Response $response)
    {
        // Rate limit handling
    }
}
```

### 3. API Client Pattern
```php
// app/Integrations/Datto/DattoApiClient.php
class DattoApiClient extends BaseApiClient
{
    protected string $baseUrl = 'https://api.datto.com/v1';
    
    public function __construct(array $credentials)
    {
        parent::__construct($credentials);
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['api_key'],
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }
    
    public function getDevices(): array
    {
        return $this->withRetry(function () {
            $response = $this->client->get('/devices');
            return $this->handleResponse($response);
        });
    }
    
    public function getAlerts(array $filters = []): array
    {
        return $this->withRetry(function () use ($filters) {
            $response = $this->client->get('/alerts', [
                'query' => $filters,
            ]);
            return $this->handleResponse($response);
        });
    }
    
    protected function handleResponse(Response $response): array
    {
        if ($response->getStatusCode() === 429) {
            $this->handleRateLimit($response);
        }
        
        return json_decode($response->getBody(), true);
    }
}
```

### 4. Sync Service
```php
// app/Integrations/Datto/DattoSyncService.php
class DattoSyncService
{
    public function __construct(
        private DattoApiClient $client,
        private Company $company
    ) {}
    
    public function syncDevices(): SyncResult
    {
        $devices = $this->client->getDevices();
        $synced = 0;
        $errors = [];
        
        foreach ($devices as $deviceData) {
            try {
                DattoDevice::updateOrCreate(
                    [
                        'company_id' => $this->company->id,
                        'external_id' => $deviceData['id'],
                    ],
                    [
                        'name' => $deviceData['name'],
                        'type' => $deviceData['type'],
                        'status' => $deviceData['status'],
                        'last_seen' => $deviceData['lastSeen'],
                        'raw_data' => $deviceData,
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                $errors[] = "Device {$deviceData['id']}: {$e->getMessage()}";
            }
        }
        
        return new SyncResult($synced, count($errors), $errors);
    }
}
```

## Common Integration Patterns

### Pattern 1: Polling (Pull)
```php
// Scheduled job runs every X hours
class SyncDattoDataJob implements ShouldQueue
{
    public function handle()
    {
        $companies = Company::whereHas('integrations', function ($q) {
            $q->where('type', 'datto')->where('enabled', true);
        })->get();
        
        foreach ($companies as $company) {
            $integration = new DattoIntegration($company);
            $integration->sync($company);
        }
    }
}
```

### Pattern 2: Webhooks (Push)
```php
// app/Http/Controllers/Webhooks/DattoWebhookController.php
class DattoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            abort(403);
        }
        
        $payload = $request->all();
        
        // Process webhook
        match ($payload['event']) {
            'device.created' => $this->handleDeviceCreated($payload),
            'device.updated' => $this->handleDeviceUpdated($payload),
            'alert.triggered' => $this->handleAlert($payload),
            default => Log::warning("Unknown webhook event: {$payload['event']}")
        };
        
        return response()->json(['status' => 'success']);
    }
    
    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Datto-Signature');
        $payload = $request->getContent();
        $secret = config('integrations.datto.webhook_secret');
        
        $expected = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expected, $signature);
    }
}
```

### Pattern 3: Real-time Sync
```php
// Immediate sync when user triggers
public function syncNow(Company $company, string $integrationType)
{
    $integration = IntegrationFactory::create($integrationType, $company);
    
    dispatch(new SyncIntegrationJob($company, $integration))
        ->onQueue('integrations');
    
    return response()->json([
        'message' => 'Sync started',
        'status' => 'queued',
    ]);
}
```

## Data Mapping Strategy

### Customer Name Matching
```php
// app/Services/CustomerMappingService.php
class CustomerMappingService
{
    public function findMatches(string $name, array $candidates): array
    {
        $matches = [];
        
        foreach ($candidates as $candidate) {
            $score = $this->calculateSimilarity($name, $candidate->name);
            
            if ($score > 0.7) {
                $matches[] = [
                    'candidate' => $candidate,
                    'score' => $score,
                    'method' => $this->getMatchMethod($name, $candidate->name),
                ];
            }
        }
        
        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $matches;
    }
    
    private function calculateSimilarity(string $a, string $b): float
    {
        // Normalize
        $a = $this->normalize($a);
        $b = $this->normalize($b);
        
        // Exact match
        if ($a === $b) return 1.0;
        
        // Levenshtein distance
        $lev = levenshtein($a, $b);
        $maxLen = max(strlen($a), strlen($b));
        $levScore = 1 - ($lev / $maxLen);
        
        // Similar text
        similar_text($a, $b, $simScore);
        $simScore = $simScore / 100;
        
        // Weighted average
        return ($levScore * 0.6) + ($simScore * 0.4);
    }
    
    private function normalize(string $str): string
    {
        $str = strtolower($str);
        $str = preg_replace('/\b(pty|ltd|inc|corp|llc|limited)\b/', '', $str);
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        return trim($str);
    }
}
```

## Rate Limiting & Retry Logic

### Exponential Backoff
```php
protected function withRetry(callable $callback, int $maxRetries = 3): mixed
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $callback();
        } catch (RateLimitException $e) {
            $attempt++;
            $waitTime = pow(2, $attempt) * 1000; // 2s, 4s, 8s
            
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            
            Log::warning("Rate limited, retrying in {$waitTime}ms (attempt {$attempt})");
            usleep($waitTime * 1000);
        } catch (ConnectionException $e) {
            $attempt++;
            
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            
            sleep(2);
        }
    }
}
```

### Rate Limit Tracking
```php
class RateLimiter
{
    public function checkLimit(string $integration, Company $company): bool
    {
        $key = "rate_limit:{$integration}:{$company->id}";
        $limit = config("integrations.{$integration}.rate_limit", 100);
        $window = config("integrations.{$integration}.rate_window", 60);
        
        $current = Cache::get($key, 0);
        
        if ($current >= $limit) {
            return false;
        }
        
        Cache::put($key, $current + 1, $window);
        return true;
    }
}
```

## Credential Management

### Encrypted Storage
```php
// app/Models/Integration.php
class Integration extends Model
{
    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'enabled' => 'boolean',
    ];
    
    public function setCredentialsAttribute(array $credentials)
    {
        // Encrypt before storing
        $this->attributes['credentials'] = encrypt($credentials);
    }
    
    public function getCredentialsAttribute($value)
    {
        // Decrypt when retrieving
        return decrypt($value);
    }
}
```

## Historical Data Backfill

### Async Backfill Job
```php
class BackfillIntegrationDataJob implements ShouldQueue
{
    public function __construct(
        private Company $company,
        private string $integrationType,
        private Carbon $startDate,
        private Carbon $endDate
    ) {}
    
    public function handle()
    {
        $integration = IntegrationFactory::create($this->integrationType, $this->company);
        
        $currentDate = $this->startDate->copy();
        
        while ($currentDate <= $this->endDate) {
            $integration->syncHistoricalData($currentDate);
            $currentDate->addDay();
            
            // Respect rate limits
            sleep(1);
        }
    }
}
```

## Testing Integrations

### Mock API Responses
```php
// tests/Feature/Integrations/DattoIntegrationTest.php
class DattoIntegrationTest extends TestCase
{
    public function test_syncs_devices()
    {
        Http::fake([
            'api.datto.com/v1/devices' => Http::response([
                ['id' => 1, 'name' => 'Device 1', 'status' => 'online'],
                ['id' => 2, 'name' => 'Device 2', 'status' => 'offline'],
            ], 200),
        ]);
        
        $company = Company::factory()->create();
        $integration = new DattoIntegration($company);
        
        $result = $integration->sync($company);
        
        expect($result->synced)->toBe(2);
        expect(DattoDevice::count())->toBe(2);
    }
}
```

## Common Tasks

### Adding New Integration
1. Create integration directory: `app/Integrations/NewIntegration/`
2. Implement `IntegrationInterface`
3. Create API client extending `BaseApiClient`
4. Create sync service
5. Create models for data storage
6. Add migration for integration tables
7. Register in `IntegrationFactory`
8. Add configuration to `config/integrations.php`
9. Create tests

### Debugging Integration Issues
- Check API credentials validity
- Review rate limit status
- Inspect raw API responses
- Check sync job queue status
- Review integration logs
- Test connection manually

### Monitoring Integration Health
- Track sync success/failure rates
- Monitor API response times
- Alert on consecutive failures
- Track data freshness
- Monitor rate limit usage

## Communication Style
- Explain integration patterns
- Provide API client examples
- Discuss rate limiting strategies
- Share data mapping techniques
- Document error handling
- Show webhook implementation
