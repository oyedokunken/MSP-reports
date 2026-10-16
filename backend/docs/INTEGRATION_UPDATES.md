# Integration System Updates - Dynamic Configuration

## Changes Made

### 1. Dynamic Integration Scanning

**Before:** Hardcoded arrays of integration types in multiple places
```php
$availableTypes = [
    'Autotask',
    'Datto',
    'ActiveDirectory',
    // ... manually maintained list
];
```

**After:** Dynamic filesystem scanning
```php
protected function scanAvailableIntegrations(): array
{
    // Scans app/Integrations directory
    // Reads config.php from each integration
    // Returns array of available integrations
}
```

### 2. Files Updated

**IntegrationController.php:**
- Added `scanAvailableIntegrations()` method
- Updated `getIntegrations()` to use dynamic scanning
- Updated `createIntegration()` to use dynamic scanning
- **Removed hardcoded integration arrays** (3 places)

**CreateIntegration Command (Artisan):**
- Scans integrations from filesystem
- Displays integration descriptions from config.php
- Dynamically reads configuration options from each integration's config
- Automatically handles all field types (text, number, boolean, password)
- Shows help text for each configuration option

### 3. Benefits

**Maintainability:**
- Add new integration = create folder + config.php
- No need to update controller arrays
- No need to update command switch statements
- Single source of truth (config.php)

**Flexibility:**
- Each integration defines its own configuration schema
- Field types, labels, defaults, help text all in config
- Automatic validation based on field types
- Supports any number of configuration options

**Developer Experience:**
- Interactive prompts read from config
- Help text displayed for each field
- Type-appropriate input (confirm for boolean, secret for password)
- Integration descriptions shown in selection menu

### 4. How It Works

**Directory Structure:**
```
app/Integrations/
├── Contracts/              # Interface definitions (skipped by scanner)
├── Autotask/
│   ├── config.php         # ← Scanned
│   ├── AutotaskIntegration.php
│   ├── AutotaskClient.php
│   └── AutotaskMapper.php
├── ActiveDirectory/
│   └── config.php         # ← Scanned
└── Datto/
    ├── config.php         # ← Scanned
    └── ...
```

**Scanning Process:**
1. Glob `app/Integrations/*` directories
2. Skip `Contracts` directory
3. Check for `config.php` in each directory
4. Load config using `require`
5. Extract integration name from directory name

**Configuration Reading:**
```php
// In config.php
return [
    'name' => 'ActiveDirectory',
    'display_name' => 'Active Directory',
    'description' => 'Integrate with on-premise Active Directory...',
    'configuration' => [
        'domain' => [
            'type' => 'text',              // text, number, boolean, password
            'label' => 'AD Domain',         // Shown in prompt
            'required' => true,             // Required field
            'default' => null,              // Default value
            'help' => 'Your AD domain...',  // Help text shown
        ],
    ],
];
```

**Command Execution:**
```bash
php artisan integration:create

# Dynamically generates prompts like:
# Your AD domain (e.g., customer.local)
# Active Directory domain:
# > tts.local
#
# Include disabled users? (yes/no) [no]:
# > no
```

### 5. Adding a New Integration (Example)

**Step 1:** Create directory
```bash
mkdir app/Integrations/MyNewIntegration
```

**Step 2:** Create config.php
```php
<?php
return [
    'name' => 'MyNewIntegration',
    'display_name' => 'My New Integration',
    'description' => 'Integrates with XYZ service',
    'configuration' => [
        'api_endpoint' => [
            'type' => 'text',
            'label' => 'API Endpoint URL',
            'required' => true,
            'help' => 'The base URL for the API',
        ],
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 3600,
        ],
        'enabled' => [
            'type' => 'boolean',
            'label' => 'Enable Sync',
            'default' => true,
        ],
    ],
    'data_types' => [...],
    'rate_limits' => [...],
];
```

**Step 3:** That's it!
- Integration immediately appears in `php artisan integration:create`
- Integration immediately available in API
- No controller changes needed
- No command changes needed

### 6. Field Types Supported

| Type | Input Method | Example |
|------|-------------|---------|
| `text` | `ask()` | Simple text input |
| `textarea` | `ask()` | Multi-line text |
| `number` | `ask()` with casting | Numeric input, cast to int |
| `boolean` | `confirm()` | Yes/no prompts |
| `password` | `secret()` | Hidden input (with security warning) |

### 7. Security Considerations

**Password Fields:**
- Command warns: "Password field should be set securely via UI"
- Asks confirmation before accepting password input
- Recommends setting via database or UI instead

**API Keys:**
- Not recommended to set via command
- Use `php artisan apikey:create` instead
- Separate, secure workflow

### 8. Backward Compatibility

**Existing Integrations:**
- All existing integrations work unchanged
- Just need config.php file (most already have it)
- Configuration section defines available options

**API Response:**
- `GET /api/integrations` returns same structure
- `POST /api/integrations` accepts same format
- Frontend UI unchanged (when built)

## Summary

**Before:** Hardcoded lists in 3+ places, manual switch statements, rigid configuration
**After:** Dynamic scanning, self-describing integrations, flexible configuration

**Developer adds new integration:**
1. Create directory in `app/Integrations/`
2. Add `config.php` with configuration schema
3. Done! 
**System automatically:**
- Discovers new integration
- Generates interactive prompts
- Validates input based on field types
- Creates integration with proper configuration
