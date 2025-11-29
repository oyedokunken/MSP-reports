<?php

return [
    'name' => 'Datto',
    'display_name' => 'Datto RMM & Backup',
    'description' => 'Integrate with Datto for backup monitoring, device management, and uptime tracking.',
    
    'credentials' => [
        // Datto RMM Credentials (Region defaults to Syrah)
        'rmm_api_key' => [
            'type' => 'text',
            'label' => 'RMM API Key (Username)',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Datto RMM API username',
        ],
        'rmm_api_secret' => [
            'type' => 'password',
            'label' => 'RMM API Secret (Password)',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Datto RMM API password',
        ],
        
        // Datto SaaS Protection Credentials
        'saas_public_key' => [
            'type' => 'text',
            'label' => 'SaaS Public Key',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Datto SaaS Protection public key',
        ],
        'saas_private_key' => [
            'type' => 'password',
            'label' => 'SaaS Private Key',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Datto SaaS Protection private key',
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
