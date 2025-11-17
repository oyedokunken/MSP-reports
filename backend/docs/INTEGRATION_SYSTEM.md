# Integration System - Developer Guide

## Overview

The MSP Reports Platform uses a modular integration framework that allows easy addition of new integrations without modifying core code. Each integration is self-contained and registers itself with the system.

## Integration Types

### **Type 1: Pull Integrations** (Platform pulls from external API)
- **Examples:** Autotask, Datto RMM, ITGlue, Barracuda
- **Flow:** Platform → External API → IntegrationCustomers → CustomerMapping
- **Requires:** API credentials, rate limiting, historical import support

### **Type 2: Push Integrations** (Agent pushes to platform)
- **Examples:** Active Directory
- **Flow:** Agent (on client infrastructure) → Platform API → Database
- **Requires:** Agent software, API key authentication, endpoint to receive data

## How to Add a New Integration

### Step 1: Create Integration Folder

```
backend/app/Integrations/{IntegrationName}/
├── config.php                    # Registration and metadata
├── {IntegrationName}Integration.php  # Main integration class (Pull only)
├── {IntegrationName}Client.php       # API client (Pull only)
└── {IntegrationName}Mapper.php       # Data transformation (Pull only)
```

**For Push integrations** (like Active Directory), you only need `config.php`.

### Step 2: Create config.php

```php
<?php

return [
    // Basic Info
    'name' => 'YourIntegration',
    'display_name' => 'Your Integration Display Name',
    'description' => 'What this integration does',
    
    // Credentials (for Pull integrations)
    'credentials' => [
        'api_key' => [
            'type' => 'password',  // text, password, textarea
            'label' => 'API Key',
            'required' => true,
            'help' => 'Your API key from...',
        ],
        'endpoint' => [
            'type' => 'text',
            'label' => 'API Endpoint',
            'required' => true,
            'default' => 'https://api.example.com/v1',
        ],
    ],
    
    // Configuration Options
    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 3600,
        ],
        'sync_tickets' => [
            'type' => 'boolean',
            'label' => 'Sync Tickets',
            'default' => true,
        ],
    ],
    
    // Data Types Provided
    'data_types' => [
        'tickets' => 'Support Tickets',
        'users' => 'User Accounts',
    ],
    
    // Rate Limiting (for Pull integrations)
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
    ],
    
    // Historical Data Support
    'supports_historical' => true,
    'historical_range_days' => 365,
    
    // Documentation
    'documentation_url' => 'https://docs.example.com',
];
```

### Step 3: Register in IntegrationController

Edit `app/Http/Controllers/Api/IntegrationController.php`:

**In `getIntegrations()` method (line ~19):**
```php
$availableTypes = [
    'Autotask',
    'Datto',
    'YourIntegration',  // Add here
];
```

**In `createIntegration()` method (line ~58):**
```php
$availableTypes = [
    'Autotask', 
    'Datto',
    'YourIntegration',  // Add here
];
```

### Step 4: (Pull Integrations Only) Implement Integration Class

```php
<?php

namespace App\Integrations\YourIntegration;

use App\Integrations\Contracts\IntegrationInterface;

class YourIntegrationIntegration implements IntegrationInterface
{
    public function authenticate(array $credentials): bool
    {
        // Validate credentials and establish connection
    }
    
    public function sync(int $integrationId): array
    {
        // Pull latest current-state data
    }
    
    public function getCustomers(): array
    {
        // Return customer list in standardized format
    }
    
    public function getHistoricalData(string $startDate, string $endDate): array
    {
        // Pull historical data for date range
    }
    
    public function healthCheck(): bool
    {
        // Test if connection still valid
    }
    
    // ... other interface methods
}
```

### Step 5: (Push Integrations Only) Create API Endpoint

1. **Create Controller:**
   ```php
   // app/Http/Controllers/Api/YourIntegrationController.php
   public function push(Request $request) {
       // Validate request
       // Process data
       // Return response
   }
   ```

2. **Add Route:**
   ```php
   // routes/api.php
   Route::post('/integrations/your-integration/push', 
       [YourIntegrationController::class, 'push']
   )->middleware([\App\Http\Middleware\ApiKeyAuthentication::class]);
   ```

3. **Create Models/Migrations** for integration-specific data storage

## Active Directory Integration Example

### What We Implemented:

1. **✅ config.php** - Registered ActiveDirectory integration
   - Location: `app/Integrations/ActiveDirectory/config.php`
   - Defines domain configuration, data types, setup instructions

2. **✅ Controller** - Handles push requests from agent
   - Location: `app/Http/Controllers/Api/ActiveDirectoryController.php`
   - Method: `push()` - receives POST from PowerShell agent

3. **✅ Service** - Processes incoming data
   - Location: `app/Services/ActiveDirectorySyncService.php`
   - Upserts users, groups, memberships

4. **✅ Models** - Database storage
   - `ActiveDirectoryUser`
   - `ActiveDirectoryGroup`
   - `ActiveDirectoryGroupMembership`

5. **✅ Migrations** - Database schema
   - 3 tables for users, groups, memberships

6. **✅ Routes** - API endpoint
   - `POST /api/integrations/active-directory/push`
   - Uses `ApiKeyAuthentication` middleware

7. **✅ Agent** - PowerShell sync agent
   - Runs on Windows Server
   - Pushes data every 4 hours (configurable)

### How It Works:

```
Windows Server (Customer)              MSP Reports Platform (Backend)
┌────────────────────────┐            ┌────────────────────────────┐
│                        │            │                            │
│  PowerShell Agent      │  HTTPS     │  POST /api/integrations/   │
│  - Collects AD data    │ ────────>  │  active-directory/push     │
│  - Runs every 4 hours  │  Bearer    │                            │
│  - Stores API key      │  {key}     │  ApiKeyAuthentication      │
│    securely            │            │         ↓                  │
│                        │            │  ActiveDirectoryController │
│                        │            │         ↓                  │
│                        │            │  ActiveDirectorySyncService│
│                        │            │         ↓                  │
│                        │            │  Database (users, groups)  │
│                        │            │                            │
└────────────────────────┘            └────────────────────────────┘
```

## Testing New Integrations

### 1. **Verify Registration**
```bash
# Should show your integration in available_types
curl http://localhost/api/integrations \
  -H "Authorization: Bearer {token}"
```

### 2. **Create Integration**
```bash
curl -X POST http://localhost/api/integrations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "integration_type": "YourIntegration",
    "display_name": "My Integration",
    "status": "active",
    "credentials": {...},
    "configuration": {...}
  }'
```

### 3. **Test Sync** (Pull integrations)
```bash
curl -X GET http://localhost/api/integrations/{id}/sync \
  -H "Authorization: Bearer {token}"
```

### 4. **Test Push** (Push integrations)
```bash
curl -X POST http://localhost/api/integrations/your-integration/push \
  -H "Authorization: Bearer {api_key}" \
  -H "Content-Type: application/json" \
  -d '{...}'
```

## Integration Licensing

Each integration requires a license. Licenses are checked in `IntegrationController::createIntegration()`:

```php
$sku = 'integration_' . strtolower($validated['integration_type']);
// e.g., 'integration_activedirectory'

$hasLicense = $company->licenses()
    ->where('status', 'active')
    ->where('product_sku', $sku)
    ->exists();
```

**License SKUs:**
- `integration_autotask`
- `integration_datto`
- `integration_activedirectory`
- etc.

## Frontend Integration Pages

Each integration should have corresponding frontend pages:

1. **Integration List** - `/integrations` (shows all configured)
2. **Add Integration** - `/integrations/add` (select type, enter credentials)
3. **Integration Detail** - `/integrations/{id}` (view sync logs, settings)
4. **Integration Data** - `/integrations/{id}/data` (view collected data)

**For Active Directory:**
- `/integrations/active-directory/users`
- `/integrations/active-directory/groups`
- `/integrations/active-directory/memberships`

## Security Considerations

1. **Credentials Storage:** Always encrypted in database
2. **API Keys:** Hashed (SHA256), never stored plaintext
3. **Company Scoping:** All queries scoped to company_id
4. **Rate Limiting:** Enforced via middleware
5. **Audit Logging:** All sync operations logged
6. **IP Whitelist:** Optional for API keys

## Summary

**Adding an integration requires:**
1. Create `config.php` (registration)
2. Add to `IntegrationController` arrays
3. **(Pull)** Implement Integration/Client/Mapper classes
4. **(Push)** Create Controller/Service/Models/Routes
5. Create database migrations (if needed)
6. Create frontend pages
7. Add documentation
8. Create license SKU

**Active Directory is complete and serves as a reference for push-based integrations!**
