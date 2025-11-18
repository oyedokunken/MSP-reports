# Dynamic Rate Limits - Configuration Guide

## Overview

Rate limits are now **completely configurable per integration** via each integration's `config.php` file. No hardcoded values in controllers!

## How It Works

### Before (Hardcoded)
```php
// In IntegrationController.php - had to manually update
$limits = [
    'Autotask' => ['per_minute' => 100, 'per_hour' => 5000],
    'Datto' => ['per_minute' => 60, 'per_hour' => 1000],
    'ConnectWise' => ['per_minute' => 120, 'per_hour' => 6000],
    // ... manual updates needed
];
```

### After (Dynamic)
```php
// Reads from integration config file automatically
$integrationConfig = $this->getIntegrationConfig($integration->integration_type);
$rateLimits = $integrationConfig['rate_limits'] ?? [];

$limit = [
    'per_minute' => $rateLimits['requests_per_minute'] ?? 60,
    'per_hour' => $rateLimits['requests_per_hour'] ?? 1000,
    'burst_allowance' => $rateLimits['burst_allowance'] ?? 10,
];
```

## Configuring Rate Limits

### Example: Autotask (config.php)
```php
<?php

return [
    'name' => 'Autotask',
    'display_name' => 'Autotask PSA',
    
    // ... other config ...
    
    'rate_limits' => [
        'requests_per_minute' => 100,
        'requests_per_hour' => 5000,
        'burst_allowance' => 20,
    ],
];
```

### Example: Active Directory (Push Integration)
```php
<?php

return [
    'name' => 'ActiveDirectory',
    'display_name' => 'Active Directory',
    
    // ... other config ...
    
    'rate_limits' => [
        // No rate limits - agent pushes to us
        'requests_per_minute' => null,
        'requests_per_hour' => null,
        'burst_allowance' => null,
    ],
];
```

### Example: High-Volume Integration
```php
<?php

return [
    'name' => 'Microsoft365',
    'display_name' => 'Microsoft 365',
    
    // ... other config ...
    
    'rate_limits' => [
        'requests_per_minute' => 200,    // Higher limit
        'requests_per_hour' => 10000,    // Much higher
        'burst_allowance' => 50,         // More burst capacity
    ],
];
```

## Rate Limit Fields

| Field | Description | Default | Required |
|-------|-------------|---------|----------|
| `requests_per_minute` | Max requests per minute | 60 | No |
| `requests_per_hour` | Max requests per hour | 1000 | No |
| `burst_allowance` | Extra requests allowed in short bursts | 10 | No |

**Note:** Set to `null` for integrations without rate limits (like push-based integrations).

## API Response

**GET /api/integrations/{id}/rate-limit**

Returns the configured limits and current usage:

```json
{
  "status": "success",
  "data": {
    "integration_type": "Autotask",
    "limits": {
      "per_minute": 100,
      "per_hour": 5000,
      "burst_allowance": 20
    },
    "current_usage": {
      "requests_last_hour": 247,
      "rate_limit_hits": 0
    },
    "percentage_used": 4.94,
    "status": "normal"
  }
}
```

## Benefits

### 1. Single Source of Truth
- Rate limits defined in one place (config.php)
- No need to update controller code
- Easy to find and modify

### 2. Integration-Specific
- Each integration can have different limits
- Reflects actual API capabilities
- Easy to adjust based on vendor changes

### 3. Automatic Discovery
- Controller automatically reads from config
- New integrations get default values
- No code changes needed

### 4. Documentation in Code
- Rate limits documented where integration is defined
- Easy to reference when debugging
- Clear what limits apply

## Adding Rate Limits to New Integration

**Step 1:** Create/edit integration config
```bash
vim app/Integrations/NewIntegration/config.php
```

**Step 2:** Add rate_limits section
```php
return [
    'name' => 'NewIntegration',
    // ... other config ...
    
    'rate_limits' => [
        'requests_per_minute' => 60,   // Check vendor docs
        'requests_per_hour' => 1000,   // Check vendor docs
        'burst_allowance' => 10,       // Typically 10-20
    ],
];
```

**Step 3:** Done!
- No controller changes needed
- Rate limits automatically enforced
- Visible in API responses

## Defaults

If `rate_limits` is not specified or fields are missing:

```php
// Fallback values
'per_minute' => 60,
'per_hour' => 1000,
'burst_allowance' => 10,
```

These are reasonable conservative defaults that work for most APIs.

## Example Integrations

### Autotask PSA
```php
'rate_limits' => [
    'requests_per_minute' => 100,
    'requests_per_hour' => 5000,
    'burst_allowance' => 20,
],
```

### Datto RMM
```php
'rate_limits' => [
    'requests_per_minute' => 60,
    'requests_per_hour' => 1000,
    'burst_allowance' => 10,
],
```

### Active Directory (Agent-Based)
```php
'rate_limits' => [
    'requests_per_minute' => null,  // No limits - we receive data
    'requests_per_hour' => null,
    'burst_allowance' => null,
],
```

## Rate Limit Enforcement

The platform uses these limits to:

1. **Throttle requests** - Delay requests when approaching limits
2. **Queue syncs** - Spread requests over time
3. **Monitor usage** - Track and report limit hits
4. **Alert admins** - Notify when limits are frequently hit
5. **Adjust timing** - Optimize sync schedules

## Monitoring

**Check current usage:**
```bash
curl -X GET https://platform/api/integrations/5/rate-limit \
  -H "Authorization: Bearer {token}"
```

**View in logs:**
```bash
tail -f storage/logs/laravel.log | grep "rate limit"
```

**Dashboard (when built):**
- Real-time rate limit status
- Historical usage graphs
- Alerts for exceeded limits

## Summary

**All rate limits are now configured per integration:**
- ✅ No hardcoded arrays in controllers
- ✅ Single source of truth (config.php)
- ✅ Easy to update and maintain
- ✅ Self-documenting
- ✅ Automatic discovery

**To change rate limits:**
1. Edit integration's `config.php`
2. Update `rate_limits` section
3. Done! Changes take effect immediately.
