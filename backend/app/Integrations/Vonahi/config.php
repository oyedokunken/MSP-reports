<?php

declare(strict_types=1);

return [
    'name' => 'Vonahi',
    'description' => 'Vonahi Security penetration testing and vulnerability assessment integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://api.vonahi.io',
            'placeholder' => 'https://api.vonahi.io',
            'help' => 'Vonahi API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'encrypted' => true,
            'placeholder' => 'Enter Vonahi API key (optional for now)',
            'help' => 'Placeholder credential until real Vonahi API details are defined.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync Vonahi data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'vulnerability_scans' => 'Vonahi Vulnerability Scans (placeholder)',
        'penetration_tests' => 'Vonahi Penetration Test Results (placeholder)',
        'security_findings' => 'Vonahi Security Findings (placeholder)',
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
