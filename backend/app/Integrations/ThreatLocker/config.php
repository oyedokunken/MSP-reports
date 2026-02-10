<?php

declare(strict_types=1);

return [
    'name' => 'ThreatLocker',
    'display_name' => 'ThreatLocker',
    'description' => 'ThreatLocker application allowlisting, ringfencing, and endpoint security',
    'version' => '2.0.0',

    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'Host Name or IP Address',
            'required' => true,
            'default' => 'https://api.g.threatlocker.com/',
            'placeholder' => 'https://api.g.threatlocker.com/',
            'help' => 'The hostname or IP address of the ThreatLocker server. The instance letter (e.g. "g") will be extracted automatically.',
        ],
        'api_user_id' => [
            'type' => 'text',
            'label' => 'API User ID',
            'required' => true,
            'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'help' => 'The API User ID (GUID) from the ThreatLocker console. Found under Administrators → API Users.',
        ],
        'api_token_name' => [
            'type' => 'text',
            'label' => 'API Token Name',
            'required' => true,
            'placeholder' => 'e.g. msp-reports@xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'help' => 'The API Token Name assigned when creating the API user.',
        ],
        'api_secret' => [
            'type' => 'password',
            'label' => 'API Secret',
            'required' => true,
            'encrypted' => true,
            'placeholder' => 'Enter your ThreatLocker API secret',
            'help' => 'The API Secret (Auth Key) generated for the API user. This is only shown once at creation time.',
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
    ],

    'data_types' => [
        'threatlocker_organization' => 'ThreatLocker Organizations',
        'threatlocker_computer' => 'ThreatLocker Computers / Endpoints',
        'threatlocker_policy' => 'ThreatLocker Application Control Policies',
        'threatlocker_application' => 'ThreatLocker Applications',
        'threatlocker_application_file' => 'ThreatLocker Application Files',
        'threatlocker_storage_policy' => 'ThreatLocker Storage Control Policies',
        'threatlocker_storage_device' => 'ThreatLocker Storage Devices',
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
        'supports_historical' => false,
        'max_range_days' => 0,
        'recommended_range_days' => 0,
    ],

    'features' => [
        'customer_mapping' => true,
        'historical_import' => false,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
