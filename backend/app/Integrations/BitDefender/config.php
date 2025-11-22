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
            'required' => false,
            'default' => 'https://cloud.gravityzone.bitdefender.com/api',
            'placeholder' => 'https://cloud.gravityzone.bitdefender.com/api',
            'help' => 'BitDefender GravityZone API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'encrypted' => true,
            'placeholder' => 'Enter BitDefender API key (optional for now)',
            'help' => 'Placeholder credential until real BitDefender API details are defined.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync BitDefender data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'endpoint_protection' => 'BitDefender Endpoint Protection Status (placeholder)',
        'threat_detections' => 'BitDefender Threat Detections (placeholder)',
        'malware_incidents' => 'BitDefender Malware Incidents (placeholder)',
        'security_events' => 'BitDefender Security Events (placeholder)',
        'device_status' => 'BitDefender Device Status (placeholder)',
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
