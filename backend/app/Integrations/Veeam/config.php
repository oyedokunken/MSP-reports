<?php

return [
    'name' => 'Veeam',
    'description' => 'Veeam Service Provider Console backup and recovery integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://api.veeam.com',
            'placeholder' => 'https://api.veeam.com',
            'help' => 'Veeam Service Provider Console API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'placeholder' => 'Enter Veeam API key (optional for now)',
            'help' => 'Placeholder credential until real Veeam API details are defined.',
        ],
        'username' => [
            'type' => 'text',
            'label' => 'Username',
            'required' => false,
            'placeholder' => 'Enter Veeam username (optional for now)',
            'help' => 'Veeam Service Provider Console username.',
        ],
        'password' => [
            'type' => 'password',
            'label' => 'Password',
            'required' => false,
            'placeholder' => 'Enter Veeam password (optional for now)',
            'help' => 'Veeam Service Provider Console password.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync Veeam data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'backup_jobs' => 'Veeam Backup Jobs (placeholder)',
        'backup_sessions' => 'Veeam Backup Sessions (placeholder)',
        'restore_sessions' => 'Veeam Restore Sessions (placeholder)',
        'protected_vms' => 'Veeam Protected VMs (placeholder)',
        'backup_repositories' => 'Veeam Backup Repositories (placeholder)',
        'job_statistics' => 'Veeam Job Statistics (placeholder)',
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
