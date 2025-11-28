<?php

return [
    'name' => 'SaaS Alerts',
    'description' => 'SaaS Alerts - Security monitoring and alerting for SaaS applications',
    'version' => '1.0.0',

    'credentials' => [
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => true,
            'placeholder' => 'Enter your SaaS Alerts API key',
            'help' => 'Found in SaaS Alerts Partner Portal. Used in api_key header for authentication.',
        ],
        'partner_id' => [
            'type' => 'text',
            'label' => 'Partner ID',
            'required' => true,
            'placeholder' => 'Enter SaaS Alerts Partner ID',
            'help' => 'Your SaaS Alerts Partner ID. Found in Partner Portal.',
        ],
        'base_url' => [
            'type' => 'select',
            'label' => 'Environment',
            'required' => true,
            'options' => [
                'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1' => 'Production',
                'https://us-central1-saas-alerts-qa.cloudfunctions.net/reportApi/api/v1' => 'QA',
                'https://us-central1-saas-alerts-dev.cloudfunctions.net/reportApi/api/v1' => 'Development',
            ],
            'default' => 'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1',
            'help' => 'Select the SaaS Alerts API environment',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync SaaS data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        'customers' => 'SaaS Alerts Customers/Organizations',
        'users' => 'SaaS Alerts Users/Accounts',
        'security_events' => 'SaaS Alerts Security Events',
        'billing' => 'SaaS Alerts Billing Data',
        'devices' => 'SaaS Alerts Device Mapping',
    ],

    'rate_limits' => [
        'requests_per_minute' => 100,
        'requests_per_hour' => 2000,
        'burst_allowance' => 20,
        'min_request_interval_ms' => 50,
        'concurrent_requests' => 5,
        'historical_import_multiplier' => 0.7,
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
        'real_time_sync' => true,
        'webhooks' => false,
    ],
];
