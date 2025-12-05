<?php

return [
    'name' => 'ITGlue',
    'display_name' => 'IT Glue',
    'description' => 'Integrate with IT Glue for documentation, asset management, SSL certificate monitoring, and domain expiration tracking.',
    
    'credentials' => [
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'ITG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'help' => 'Your IT Glue API key (starts with ITG.)',
        ],
        'base_url' => [
            'type' => 'select',
            'label' => 'Region',
            'required' => true,
            'options' => [
                'https://api.itglue.com' => 'United States',
                'https://api.au.itglue.com' => 'Australia',
                'https://api.eu.itglue.com' => 'Europe',
            ],
            'default' => 'https://api.au.itglue.com',
            'help' => 'Select the region that matches your IT Glue account',
        ],
    ],
    
    'configuration' => [
        'sync_frequency' => [
            'type' => 'select',
            'label' => 'Sync Frequency',
            'options' => [
                '4_hours' => 'Every 4 Hours',
                '6_hours' => 'Every 6 Hours',
                '12_hours' => 'Every 12 Hours',
                '24_hours' => 'Daily',
            ],
            'default' => '6_hours',
        ],
        'monitor_ssl_certificates' => [
            'type' => 'checkbox',
            'label' => 'Monitor SSL Certificates',
            'default' => true,
            'help' => 'Track SSL certificate expiration dates',
        ],
        'monitor_domains' => [
            'type' => 'checkbox',
            'label' => 'Monitor Domain Registrations',
            'default' => true,
            'help' => 'Track domain registration expiration dates',
        ],
        'monitor_flexible_assets' => [
            'type' => 'checkbox',
            'label' => 'Monitor Flexible Assets',
            'default' => true,
            'help' => 'Track flexible assets with expiry dates',
        ],
        'expiry_warning_days' => [
            'type' => 'number',
            'label' => 'Expiry Warning Days',
            'default' => 90,
            'help' => 'Number of days before expiry to start monitoring (default: 90)',
        ],
    ],
    
    'data_types' => [
        'organization_info' => 'Organization Information',
        'ssl_certificate' => 'SSL Certificate Monitoring',
        'domain' => 'Domain Registration Monitoring',
        'flexible_asset' => 'Flexible Asset Documentation',
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 1, // Captures current state snapshot
    
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
        'delay_between_pages_ms' => 100,
    ],
];
