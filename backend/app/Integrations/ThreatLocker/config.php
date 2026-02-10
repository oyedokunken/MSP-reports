<?php

declare(strict_types=1);

return [
    'name' => 'ThreatLocker',
    'description' => 'ThreatLocker application allowlisting and ringfencing integration',
    'version' => '1.0.0',

    // Credential fields for ThreatLocker authentication
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => true,
            'default' => 'https://api.g.threatlocker.com/',
            'placeholder' => 'https://api.g.threatlocker.com/',
            'help' => 'ThreatLocker API base URL. Use the region-specific URL provided (e.g., api.g, api.eu, api.au).',
        ],
        'api_user_id' => [
            'type' => 'text',
            'label' => 'API User ID',
            'required' => true,
            'placeholder' => 'Enter ThreatLocker API User ID',
            'help' => 'The GUID identifier for the API User.',
        ],
        'api_secret' => [
            'type' => 'password',
            'label' => 'API Secret',
            'required' => true,
            'placeholder' => 'Enter ThreatLocker API Secret',
            'help' => 'The Secret key/Token generated for the API User.',
        ],
        'api_token_name' => [
            'type' => 'text',
            'label' => 'API Token Name',
            'required' => false,
            'placeholder' => 'Enter API Token Name',
            'help' => 'The name given to the API token (e.g., msp-reports@...)',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync ThreatLocker data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        'allowlist_policies' => 'ThreatLocker Allowlist Policies',
        'ringfencing_policies' => 'ThreatLocker Ringfencing Policies',
        'blocked_applications' => 'ThreatLocker Blocked Applications',
        'security_events' => 'ThreatLocker Security Events',
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
