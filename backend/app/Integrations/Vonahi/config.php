<?php

declare(strict_types=1);

return [
    'name' => 'Vonahi',
    'description' => 'Vonahi vPenTest automated network penetration testing and vulnerability assessment',
    'version' => '1.0.0',

    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://app.vpentest.io/api/v3',
            'placeholder' => 'https://app.vpentest.io/api/v3',
            'help' => 'Vonahi vPenTest API v3 URL. Usually no change needed.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your vPenTest API key',
            'help' => 'Generate from your vPenTest profile drop-down → Generate API Key.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync Vonahi data (default: daily).',
        ],
    ],

    'data_types' => [
        'vonahi_assessment' => 'Vonahi Penetration Test Assessments',
        'vonahi_finding' => 'Vonahi Security Findings',
        'vonahi_report' => 'Vonahi Reports',
        'vonahi_agent' => 'Vonahi Agents',
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
        'supports_historical' => true,
        'max_range_days' => 365,
        'recommended_range_days' => 90,
    ],

    'features' => [
        'customer_mapping' => true,
        'historical_import' => true,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
