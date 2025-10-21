<?php

return [
    'name' => 'Datto',
    'display_name' => 'Datto RMM & Backup',
    'description' => 'Integrate with Datto for backup monitoring, device management, and uptime tracking.',
    
    'credentials' => [
        'api_key' => [
            'type' => 'text',
            'label' => 'API Key',
            'required' => true,
            'encrypted' => true,
        ],
        'base_url' => [
            'type' => 'text',
            'label' => 'API Base URL',
            'required' => false,
            'default' => 'https://api.datto.com',
        ],
    ],
    
    'configuration' => [
        'sync_frequency' => [
            'type' => 'select',
            'label' => 'Sync Frequency',
            'options' => [
                '1_hour' => 'Every Hour',
                '4_hours' => 'Every 4 Hours',
                '12_hours' => 'Every 12 Hours',
                '24_hours' => 'Daily',
            ],
            'default' => '4_hours',
        ],
    ],
    
    'data_types' => [
        'backup_status' => 'Backup Status & History',
        'device_status' => 'Device Status & Monitoring',
        'uptime_metrics' => 'Uptime & Availability Metrics',
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 90,
    
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
    ],
];
