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
            'placeholder' => 'https://data.barracudanetworks.com/b1',
            'help' => 'BarracudaONE API endpoint (default: https://data.barracudanetworks.com/b1)',
        ],
        'client_id' => [
            'type' => 'text',
            'label' => 'Client ID',
            'required' => true,
            'help' => 'Your BarracudaONE App Client ID (from Settings > Apps & Credentials)',
        ],
        'client_secret' => [
            'type' => 'password',
            'label' => 'Client Secret',
            'required' => true,
            'encrypted' => true,
            'help' => 'Your BarracudaONE App Client Secret',
        ],
        'parent_account_id' => [
            'type' => 'number',
            'label' => 'Parent Account ID',
            'required' => true,
            'help' => 'Your Partner Account ID (found in Settings > Apps & Credentials)',
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
