<?php

return [
    'name' => 'Barracuda',
    'display_name' => 'Barracuda Email Security',
    'description' => 'Integrate with Barracuda for email security monitoring, threat detection, and compliance reporting.',
    
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'placeholder' => 'https://api.barracuda.com',
            'help' => 'Your Barracuda API endpoint',
        ],
        'api_key' => [
            'type' => 'text',
            'label' => 'API Key',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Barracuda API key',
        ],
        'api_secret' => [
            'type' => 'password',
            'label' => 'API Secret',
            'required' => false,
            'encrypted' => true,
            'help' => 'Your Barracuda API secret',
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
        'email_security' => 'Email Security & Threat Detection',
        'spam_filtering' => 'Spam Filtering Statistics',
        'compliance_reports' => 'Compliance & Audit Reports',
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 90,
    
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
    ],
];
