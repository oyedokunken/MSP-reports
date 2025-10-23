<?php

return [
    'name' => 'Autotask',
    'display_name' => 'Autotask PSA',
    'description' => 'Integrate with Autotask PSA for ticket management, time tracking, billing, and customer data.',
    
    'credentials' => [
        'username' => [
            'type' => 'text',
            'label' => 'API Username',
            'required' => true,
            'help' => 'Your Autotask API username',
        ],
        'secret' => [
            'type' => 'password',
            'label' => 'API Secret',
            'required' => true,
            'help' => 'Your Autotask API secret key',
        ],
        'api_integration_code' => [
            'type' => 'text',
            'label' => 'API Integration Code',
            'required' => true,
            'help' => 'Your Autotask API Integration Code',
        ],
        'zone' => [
            'type' => 'select',
            'label' => 'API Zone',
            'required' => true,
            'default' => 'webservices2',
            'options' => [
                'webservices' => 'Zone 1 (webservices.autotask.net)',
                'webservices2' => 'Zone 2 (webservices2.autotask.net)',
                'webservices3' => 'Zone 3 (webservices3.autotask.net)',
                'webservices4' => 'Zone 4 (webservices4.autotask.net)',
                'webservices5' => 'Zone 5 (webservices5.autotask.net)',
            ],
            'help' => 'Select your Autotask API zone',
        ],
    ],
    
    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 3600,
            'help' => 'How often to sync data (minimum 3600 seconds / 1 hour)',
        ],
        'sync_tickets' => [
            'type' => 'boolean',
            'label' => 'Sync Tickets',
            'default' => true,
        ],
        'sync_time_entries' => [
            'type' => 'boolean',
            'label' => 'Sync Time Entries',
            'default' => true,
        ],
        'sync_contracts' => [
            'type' => 'boolean',
            'label' => 'Sync Contracts',
            'default' => false,
        ],
    ],
    
    'data_types' => [
        'tickets' => 'Support Tickets',
        'time_entries' => 'Time Tracking',
        'contracts' => 'Service Contracts',
        'billing' => 'Billing Information',
        'service_calls' => 'Service Call Records',
    ],
    
    'rate_limits' => [
        'requests_per_minute' => 100,
        'requests_per_hour' => 5000,
        'burst_allowance' => 20,
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 365,
    
    'documentation_url' => 'https://ww4.autotask.net/help/DeveloperHelp/Content/APIs/REST/API_Calls/REST_API_Calls.htm',
];
