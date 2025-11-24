<?php

return [
    'name' => 'RocketCyber',
    'description' => 'RocketCyber security monitoring and threat detection integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://api.rocketcyber.com',
            'placeholder' => 'https://api.rocketcyber.com',
            'help' => 'RocketCyber API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'placeholder' => 'Enter RocketCyber API key (optional for now)',
            'help' => 'Placeholder credential until real RocketCyber API details are defined.',
        ],
        'account_id' => [
            'type' => 'text',
            'label' => 'Account ID',
            'required' => false,
            'placeholder' => 'Enter Account ID (optional for now)',
            'help' => 'RocketCyber account identifier.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync RocketCyber data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'security_incidents' => 'RocketCyber Security Incidents (placeholder)',
        'threat_detections' => 'RocketCyber Threat Detections (placeholder)',
        'security_alerts' => 'RocketCyber Security Alerts (placeholder)',
        'endpoint_events' => 'RocketCyber Endpoint Events (placeholder)',
        'vulnerability_scans' => 'RocketCyber Vulnerability Scans (placeholder)',
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
