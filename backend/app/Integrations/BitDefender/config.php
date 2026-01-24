<?php

return [
    'name' => 'BitDefender',
    'description' => 'BitDefender endpoint security and antivirus integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => true,
            'default' => 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc',
            'placeholder' => 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc',
            'help' => 'BitDefender GravityZone API URL',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your BitDefender API Key',
            'help' => 'Found in GravityZone Control Center under My Account > API Keys',
        ],
    ],

    'configuration' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'default' => 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc',
            'required' => false,
        ],
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync BitDefender data (in seconds)',
        ],
        'sync_endpoints' => [
            'type' => 'boolean',
            'label' => 'Sync Endpoints',
            'default' => true,
            'help' => 'Enable syncing of endpoint protection status',
        ],
    ],

    'data_types' => [
        'bitdefender_endpoint' => 'BitDefender Endpoint Protection Status',
        'bitdefender_threat_detection' => 'BitDefender Threat Detections',
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
        'customer_mapping' => false,
        'historical_import' => true,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
