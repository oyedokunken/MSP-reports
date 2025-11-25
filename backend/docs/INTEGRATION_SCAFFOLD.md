# Integration Scaffold Guide

## Overview

This guide explains how to create a new integration scaffold (placeholder) for integrations where you don't yet have API credentials or full implementation details. This allows the integration to be recognized by the system without breaking functionality.

## Purpose of Integration Scaffolds

Integration scaffolds serve several purposes:

1. **Reserve the integration name** - Prevents conflicts when the real implementation is added
2. **Allow UI selection** - Integration appears in dropdowns and selection menus
3. **Non-blocking stubs** - Returns success for connection tests so UI doesn't block
4. **Documentation placeholder** - Documents what data types the integration will eventually provide
5. **License SKU preparation** - Enables license SKU creation (`integration_<lowercase_name>`)
6. **Future-ready** - Easy to flesh out when real API details become available

## When to Create a Scaffold

Create an integration scaffold when:

- You know you'll need the integration but don't have credentials yet
- You want to prepare the system for a future integration
- You're planning licensing/pricing before implementation
- You want to test the integration discovery system
- You need a placeholder for documentation or planning purposes

## Integration Scaffold Structure

Every integration scaffold requires **4 files** in a dedicated directory:

```
app/Integrations/<IntegrationName>/
├── config.php                    # Integration metadata and configuration
├── <IntegrationName>Client.php   # API client stub
├── <IntegrationName>Mapper.php   # Data transformation stub
└── <IntegrationName>Integration.php  # Main integration class stub
```

## File Templates

### 1. config.php

This file defines the integration's metadata, credentials, and capabilities.

```php
<?php

return [
    'name' => 'IntegrationName',
    'description' => 'Brief description of what this integration does (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://api.example.com',
            'placeholder' => 'https://api.example.com',
            'help' => 'API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'placeholder' => 'Enter API key (optional for now)',
            'help' => 'Placeholder credential until real API details are defined.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'example_data_type' => 'Example Data Type (placeholder)',
    ],

    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
        'min_request_interval_ms' => 100,
        'concurrent_requests' => 3,
        'historical_import_multiplier' => 0.5,
    ],

    'sync_frequency' => [
        'recommended' => 'daily',
        'minimum' => 'daily',
        'maximum' => 'weekly',
    ],

    'historical_data' => [
        'supports_historical' => false,
        'max_range_days' => 0,
        'recommended_range_days' => 0,
    ],

    'features' => [
        'customer_mapping' => false,
        'historical_import' => false,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
```

### 2. Client.php

The client handles API communication. For scaffolds, it returns placeholder data.

```php
<?php

namespace App\Integrations\IntegrationName;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class IntegrationNameClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.example.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('IntegrationNameClient::testConnection called - IntegrationName integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while this is a stub
        return true;
    }

    /**
     * Placeholder for future API calls.
     */
    public function getData(): array
    {
        Log::info('IntegrationNameClient::getData called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
```

### 3. Mapper.php

The mapper transforms API data into standardized format. For scaffolds, it returns placeholder structures.

```php
<?php

namespace App\Integrations\IntegrationName;

class IntegrationNameMapper
{
    /**
     * Map data into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapData(array $data): array
    {
        return [
            // No real schema yet; filled in once API is defined
            'integration_customer_id' => null,
            'data_type' => 'integrationname_data',
            'timestamp' => now(),
            'payload' => $data,
        ];
    }
}
```

### 4. Integration.php

The main integration class implements `IntegrationInterface`. For scaffolds, all methods return placeholder responses.

```php
<?php

namespace App\Integrations\IntegrationName;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IntegrationNameIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected IntegrationNameClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new IntegrationNameClient($integration->credentials ?? [], $rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        Log::info('IntegrationNameIntegration::sync called - not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => ['IntegrationName integration not implemented yet'],
        ];
    }

    public function getCustomers(): array
    {
        // No customer concept defined yet
        return [];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'data' => [],
            'errors' => ['IntegrationName historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'IntegrationName',
            'description' => 'Brief description (placeholder)',
            'data_types' => [
                'integrationname_data',
            ],
            'supports_historical' => false,
            'historical_range_days' => 0,
        ];
    }

    public function disconnect(): void
    {
        Log::info('IntegrationNameIntegration::disconnect called - nothing to do for placeholder integration', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }
}
```

## Step-by-Step: Creating a New Scaffold

### Example: Creating "Kemp" Integration Scaffold

**Step 1:** Create the directory
```bash
mkdir -p backend/app/Integrations/Kemp
```

**Step 2:** Create `config.php`
```bash
# Copy template and customize for Kemp
# Set name: 'Kemp'
# Set description: 'Kemp Load Balancer integration'
# Set data_types: load_balancer_stats, virtual_services, health_checks
```

**Step 3:** Create `KempClient.php`
```bash
# Copy template
# Replace IntegrationName with Kemp
# Update namespace to App\Integrations\Kemp
```

**Step 4:** Create `KempMapper.php`
```bash
# Copy template
# Replace IntegrationName with Kemp
# Update namespace to App\Integrations\Kemp
```

**Step 5:** Create `KempIntegration.php`
```bash
# Copy template
# Replace IntegrationName with Kemp
# Update namespace to App\Integrations\Kemp
```

**Step 6:** Verify
```bash
# Integration is automatically discovered
php artisan integration:create
# Should show "Kemp" in the list
```

**Step 7:** Commit
```bash
git add backend/app/Integrations/Kemp/
git commit -m "Add Kemp integration scaffold"
git push origin main
```

## Fully Implemented Integrations

The following integrations are **fully implemented** with real API credentials and working functionality:

### Datto RMM & Backup
- **Directory:** `app/Integrations/Datto/`
- **Purpose:** Backup monitoring, device management, and uptime tracking
- **Data Types:** backup_status, device_status, uptime_metrics
- **Status:** ✅ **Fully Implemented**
- **Credentials Required:** 
  - RMM API URL, API Key, API Secret
  - SaaS Public Key, Private Key (optional)
- **Features:** Historical data support (90 days), customer mapping, real-time sync
- **Rate Limits:** 60/min, 1000/hour

### Autotask PSA
- **Directory:** `app/Integrations/Autotask/`
- **Purpose:** Ticket management, time tracking, billing, and customer data
- **Data Types:** tickets, time_entries, contracts, billing, service_calls
- **Status:** ✅ **Fully Implemented**
- **Credentials Required:** API Username, API Secret, API Integration Code, API Endpoint
- **Features:** Historical data support (365 days), customer mapping, configurable sync options
- **Rate Limits:** 100/min, 5000/hour
- **Documentation:** [Autotask REST API](https://ww4.autotask.net/help/DeveloperHelp/Content/APIs/REST/API_Calls/REST_API_Calls.htm)

### Sendmarc
- **Directory:** `app/Integrations/Sendmarc/`
- **Purpose:** DMARC email security and monitoring
- **Data Types:** domains, sender_statistics, volume_totals, volume_timeline, ip_address_statistics
- **Status:** ✅ **Fully Implemented**
- **Credentials Required:** Bearer Token, API Endpoint (optional)
- **Features:** Historical data support (90 days), customer mapping, configurable data sync
- **Rate Limits:** 60/min, 1000/hour

### IT Glue
- **Directory:** `app/Integrations/ITGlue/`
- **Purpose:** Documentation, asset management, SSL certificate monitoring, domain expiration tracking
- **Data Types:** organization_info, ssl_certificate, domain, flexible_asset
- **Status:** ✅ **Fully Implemented**
- **Credentials Required:** API Key (starts with ITG.), Region selection (US/AU/EU)
- **Features:** SSL certificate monitoring, domain expiration tracking, flexible asset monitoring
- **Rate Limits:** 60/min, 1000/hour
- **Note:** No historical data support (current state only)

---

## Existing Integration Scaffolds

The following integration scaffolds have been created (awaiting API credentials for full implementation):

### AdminDroid
- **Directory:** `app/Integrations/AdminDroid/`
- **Purpose:** Microsoft 365 reporting and analytics
- **Data Types:** m365_reports, user_activity, mailbox_reports, license_reports, security_reports
- **Status:** Scaffold only, awaiting API credentials

### Kaseya
- **Directory:** `app/Integrations/Kaseya/`
- **Purpose:** Kaseya GRC (Governance, Risk, and Compliance) monitoring
- **Data Types:** compliance_assessments, risk_assessments, policy_compliance, audit_reports, vulnerability_assessments, remediation_tasks
- **Status:** Scaffold only, awaiting API credentials

### Kemp Load Balancer
- **Directory:** `app/Integrations/Kemp/`
- **Purpose:** Load balancer monitoring and statistics
- **Data Types:** load_balancer_stats, virtual_services, health_checks
- **Status:** Scaffold only, awaiting API credentials

### Vonahi Security
- **Directory:** `app/Integrations/Vonahi/`
- **Purpose:** Penetration testing and vulnerability assessment
- **Data Types:** vulnerability_scans, penetration_tests, security_findings
- **Status:** Scaffold only, awaiting API credentials

### ThreatLocker
- **Directory:** `app/Integrations/ThreatLocker/`
- **Purpose:** Application allowlisting and ringfencing
- **Data Types:** allowlist_policies, ringfencing_policies, blocked_applications, security_events
- **Status:** Scaffold only, awaiting API credentials

### Veeam
- **Directory:** `app/Integrations/Veeam/`
- **Purpose:** Veeam Service Provider Console backup and recovery monitoring
- **Data Types:** backup_jobs, backup_sessions, restore_sessions, protected_vms, backup_repositories, job_statistics
- **Status:** Scaffold only, awaiting API credentials

### BitDefender
- **Directory:** `app/Integrations/BitDefender/`
- **Purpose:** Endpoint security and antivirus protection
- **Data Types:** endpoint_protection, threat_detections, malware_incidents, security_events, device_status
- **Status:** Scaffold only, awaiting API credentials

### M365
- **Directory:** `app/Integrations/M365/`
- **Purpose:** Microsoft 365 audit logs and security monitoring
- **Data Types:** audit_logs, security_alerts, user_activity, mailbox_activity, sharepoint_activity
- **Status:** Scaffold only, awaiting API credentials
- **Note:** Requires Azure AD app registration with tenant_id, client_id, and client_secret

### RocketCyber
- **Directory:** `app/Integrations/RocketCyber/`
- **Purpose:** Security monitoring and threat detection
- **Data Types:** security_incidents, threat_detections, security_alerts, endpoint_events, vulnerability_scans
- **Status:** Scaffold only, awaiting API credentials

### SAT
- **Directory:** `app/Integrations/SAT/`
- **Purpose:** Security Awareness Training platform monitoring
- **Data Types:** training_completions, phishing_simulations, user_scores, course_enrollments, compliance_status
- **Status:** Scaffold only, awaiting API credentials

### SaaS
- **Directory:** `app/Integrations/SaaS/`
- **Purpose:** SaaS application monitoring and management
- **Data Types:** application_usage, license_management, user_access, subscription_status, cost_tracking
- **Status:** Scaffold only, awaiting API credentials

### WatchguardAuthPoint
- **Directory:** `app/Integrations/WatchguardAuthPoint/`
- **Purpose:** WatchGuard AuthPoint multi-factor authentication monitoring
- **Data Types:** mfa_authentications, user_enrollments, authentication_events, security_events, device_registrations
- **Status:** Scaffold only, awaiting API credentials

### Barracuda
- **Directory:** `app/Integrations/Barracuda/`
- **Purpose:** Email security and spam filtering
- **Data Types:** email_security, spam_stats, threat_detection
- **Status:** Partial implementation with mock data support
- **Note:** Has real implementation structure but uses mock data when credentials start with `test_`

## Converting Scaffold to Real Implementation

When you receive API credentials and documentation:

### Step 1: Update config.php
```php
'credentials' => [
    'api_url' => [
        'type' => 'text',
        'label' => 'API URL',
        'required' => true,  // ← Change to true
        'default' => 'https://api.realendpoint.com',  // ← Update to real endpoint
        'placeholder' => 'https://api.realendpoint.com',
        'help' => 'Your API endpoint URL',
    ],
    'api_key' => [
        'type' => 'password',
        'label' => 'API Key',
        'required' => true,  // ← Change to true
        'help' => 'Your API key from the provider dashboard',
    ],
],
```

### Step 2: Implement Client.php
```php
public function testConnection(): bool
{
    try {
        // Make real API call
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->credentials['api_key'],
        ])->get($this->baseUrl . '/health');

        return $response->successful();
    } catch (\Exception $e) {
        Log::error('Connection test failed', ['error' => $e->getMessage()]);
        return false;
    }
}

public function getData(): array
{
    // Implement real API call
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->credentials['api_key'],
    ])->get($this->baseUrl . '/data');

    return $response->json();
}
```

### Step 3: Implement Mapper.php
```php
public static function mapData(array $data): array
{
    return [
        'integration_customer_id' => $data['customer_id'] ?? null,
        'data_type' => 'real_data_type',
        'timestamp' => Carbon::parse($data['timestamp']),
        'payload' => [
            'field1' => $data['field1'],
            'field2' => $data['field2'],
            // Map real API fields
        ],
    ];
}
```

### Step 4: Implement Integration.php
```php
public function sync(): array
{
    $data = $this->client->getData();
    $mapped = IntegrationNameMapper::mapData($data);
    
    // Store in database
    // Process customers
    // Return real statistics
    
    return [
        'records_synced' => count($data),
        'records_new' => $newCount,
        'customers_mapped' => $mappedCustomers,
        'records_failed' => 0,
        'errors' => [],
    ];
}
```

## Best Practices

### DO:
- ✅ Use descriptive integration names (PascalCase)
- ✅ Set `required: false` for credentials in scaffolds
- ✅ Return `true` from `testConnection()` in scaffolds
- ✅ Log all method calls for debugging
- ✅ Document expected data types in config
- ✅ Use placeholder API URLs that are clearly fake
- ✅ Add helpful comments explaining scaffold status

### DON'T:
- ❌ Make real API calls in scaffolds
- ❌ Throw exceptions from scaffold methods
- ❌ Leave credentials as `required: true` in scaffolds
- ❌ Hardcode real API keys or secrets
- ❌ Skip implementing all IntegrationInterface methods
- ❌ Forget to update namespace in all 4 files

## Testing Your Scaffold

### Via Artisan Command
```bash
php artisan integration:create
# Select your new integration from the list
# Fill in placeholder values
# Should create successfully
```

### Via API
```bash
# Get available integrations
curl http://localhost:8000/api/integrations

# Should include your new integration in the list
```

### Via Database
```bash
php artisan tinker
>>> Integration::where('integration_type', 'YourIntegration')->first()
# Should return the integration record
```

## Troubleshooting

### Integration Not Appearing in List
- Check that `config.php` exists and returns an array
- Verify directory name matches integration name
- Check file permissions (should be readable)
- Clear Laravel cache: `php artisan config:clear`

### Namespace Errors
- Ensure all 4 files use correct namespace
- Namespace should match directory: `App\Integrations\IntegrationName`
- Class names should match file names

### Interface Errors
- Ensure Integration class implements `IntegrationInterface`
- All interface methods must be implemented
- Check method signatures match interface exactly

## Summary

Integration scaffolds allow you to:
1. Reserve integration names before implementation
2. Prepare licensing and pricing structures
3. Enable UI development without blocking on API access
4. Document planned integrations and their capabilities
5. Easily convert to real implementations when ready

The scaffold pattern ensures the system remains flexible and non-blocking while integrations are being planned or awaiting credentials.
