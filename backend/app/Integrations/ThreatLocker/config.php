<?php

declare(strict_types=1);

return [
    'name' => 'ThreatLocker',
    'display_name' => 'ThreatLocker',
    'description' => 'ThreatLocker application allowlisting, ringfencing, and endpoint security',
    'version' => '2.0.0',

    'credentials' => [
        'api_url' => [
            'type' => 'select',
            'label' => 'API Endpoint',
            'required' => true,
            'default' => 'https://portalapi.au1.threatlocker.com/portalapi/Computer/ComputerGetByAllParameters',
            'options' => [
                [
                    'value' => 'https://portalapi.au1.threatlocker.com/portalapi/Computer/ComputerGetByAllParameters',
                    'label' => 'AU1 (Australia)',
                ],
                [
                    'value' => 'https://portalapi.au2.threatlocker.com/portalapi/Computer/ComputerGetByAllParameters',
                    'label' => 'AU2 (Australia 2) - Coming Soon',
                    'disabled' => true,
                ],
                [
                    'value' => 'https://portalapi.au3.threatlocker.com/portalapi/Computer/ComputerGetByAllParameters',
                    'label' => 'AU3 (Australia 3) - Coming Soon',
                    'disabled' => true,
                ],
            ],
            'help' => 'Select the ThreatLocker Portal API endpoint for your region.',
        ],
        'api_token' => [
            'type' => 'password',
            'label' => 'Authorization Token',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your ThreatLocker Authorization Token',
            'help' => 'The Authorization header value found in your API request headers.',
        ],
        'managed_organization_id' => [
            'type' => 'text',
            'label' => 'Managed Organization ID',
            'required' => true,
            'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'help' => 'The managedOrganizationId header value found in your API request headers.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 21600, // 6 hours
            'help' => 'How often to sync ThreatLocker data (in seconds).',
        ],
        'sync_computers' => [
            'type' => 'boolean',
            'label' => 'Sync Computers',
            'default' => true,
            'help' => 'Whether to sync computer/endpoint data from ThreatLocker.',
        ],
        'sync_policies' => [
            'type' => 'boolean',
            'label' => 'Sync Policies',
            'default' => true,
            'help' => 'Whether to sync application control policies from ThreatLocker.',
        ],
        'search_by' => [
            'type' => 'select',
            'label' => 'Search By',
            'required' => true,
            'default' => 1,
            'options' => [
                ['value' => 1, 'label' => 'Computer Name'],
                ['value' => 2, 'label' => 'Organization'],
            ],
            'help' => 'Select the search criteria for fetching computers.',
        ],
    ],

    'data_types' => [
        'threatlocker_organization' => 'ThreatLocker Organizations',
        'threatlocker_computer' => 'ThreatLocker Computers / Endpoints',
        'threatlocker_policy' => 'ThreatLocker Application Control Policies',
        'threatlocker_application' => 'ThreatLocker Applications',
        'threatlocker_application_file' => 'ThreatLocker Application Files',
        'threatlocker_storage_policy' => 'ThreatLocker Storage Control Policies',
        'threatlocker_storage_device' => 'ThreatLocker Storage Devices',
        'threatlocker_audit' => 'ThreatLocker Audit Logs (Historical)',
        'threatlocker_computer_history' => 'ThreatLocker Computer Inventory (Historical)',
        'threatlocker_system_audit' => 'ThreatLocker System Audit (Logins)',
    ],

    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
        'min_request_interval_ms' => 200,
        'concurrent_requests' => 3,
        'historical_import_multiplier' => 0.5,
    ],

    'sync_frequency' => [
        'recommended' => 'every_6_hours',
        'minimum' => 'every_6_hours',
        'maximum' => 'daily',
    ],

    'historical_data' => [
        'supports_historical' => true,
        'max_range_days' => 90,
        'recommended_range_days' => 30,
    ],

    'features' => [
        'customer_mapping' => true,
        'historical_import' => true,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
