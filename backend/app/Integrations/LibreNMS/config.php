<?php

return [
    'name' => 'LibreNMS',
    'display_name' => 'LibreNMS',
    'description' => 'Integrate with LibreNMS for network device monitoring, availability tracking, and uptime reporting.',
    
    'credentials' => [
        'api_token' => [
            'type' => 'password',
            'label' => 'API Token',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your LibreNMS API token',
            'help' => 'Your LibreNMS API token from Settings > API',
        ],
        'base_url' => [
            'type' => 'text',
            'label' => 'Base URL',
            'required' => true,
            'hidden' => true,
            'default' => 'https://nms.arafuracloud.com.au/api/v0',
            'placeholder' => 'https://nms.arafuracloud.com.au/api/v0',
            'help' => 'LibreNMS API base URL (e.g., https://nms.arafuracloud.com.au/api/v0 or https://nms.example.com/api/v0)',
        ],
    ],
    
    'configuration' => [
        'sync_frequency' => [
            'type' => 'select',
            'label' => 'Sync Frequency',
            'options' => [
                '15_minutes' => 'Every 15 Minutes',
                '30_minutes' => 'Every 30 Minutes',
                '1_hour' => 'Every Hour',
                '4_hours' => 'Every 4 Hours',
            ],
            'default' => '1_hour',
        ],
        'sync_device_groups' => [
            'type' => 'checkbox',
            'label' => 'Sync Device Groups as Customers',
            'default' => true,
            'help' => 'Import device groups and map them to customers',
        ],
        'track_availability' => [
            'type' => 'checkbox',
            'label' => 'Track Device Availability',
            'default' => true,
            'help' => 'Monitor device uptime and availability metrics',
        ],
        'availability_periods' => [
            'type' => 'multiselect',
            'label' => 'Availability Periods',
            'options' => [
                '24h' => '24 Hours',
                '7d' => '7 Days',
                '30d' => '30 Days',
                '365d' => '365 Days',
            ],
            'default' => ['24h', '7d', '30d'],
            'help' => 'Which availability periods to track',
        ],
    ],
    
    'data_types' => [
        'device_group' => 'Device Groups',
        'device' => 'Network Devices',
        'device_availability' => 'Device Availability Metrics',
        'device_uptime' => 'Device Uptime Data',
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 365,
    
    'rate_limits' => [
        'requests_per_minute' => 120,
        'requests_per_hour' => 5000,
        'burst_allowance' => 20,
    ],
];
