<?php

declare(strict_types=1);

return [
    'name' => 'Autotask',
    'display_name' => 'Autotask PSA',
    'description' => 'Integrate with Autotask PSA for ticket management, time tracking, billing, and customer data.',

    'credentials' => [
        'username' => [
            'type' => 'text',
            'label' => 'API Username',
            'required' => true,
            'encrypted' => true,
            'help' => 'Your Autotask API username (email)',
        ],
        'secret' => [
            'type' => 'password',
            'label' => 'API Secret',
            'required' => true,
            'encrypted' => true,
            'help' => 'Your Autotask API secret key',
        ],
        'api_integration_code' => [
            'type' => 'password',
            'label' => 'API Integration Code',
            'required' => true,
            'encrypted' => true,
            'help' => 'Your Autotask API Integration Code',
        ],
        'endpoint' => [
            'type' => 'text',
            'label' => 'API Endpoint',
            'required' => true,
            'default' => 'https://webservices26.autotask.net/atservicesrest/v1.0',
            'help' => 'Your Autotask API endpoint URL (e.g., https://webservices26.autotask.net/atservicesrest/v1.0)',
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
