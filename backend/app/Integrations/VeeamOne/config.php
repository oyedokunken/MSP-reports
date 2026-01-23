<?php

return [
    'name' => 'Veeam ONE',
    'description' => 'Veeam ONE Reporting Server integration for backup monitoring, job status, alarms, and repository tracking.',
    'version' => '1.0.0',

    'credentials' => [
        'server_url' => [
            'type' => 'text',
            'label' => 'Server URL',
            'required' => true,
            'placeholder' => 'https://192.168.10.62:1239',
            'help' => 'Veeam ONE Reporting Server URL including port (default port: 1239). Example: https://veeamone.acme.com:1239',
        ],
        'username' => [
            'type' => 'text',
            'label' => 'Username',
            'required' => true,
            'placeholder' => 'DOMAIN\\username or username',
            'help' => 'Domain user account with Veeam ONE Administrator or Read-Only User role.',
        ],
        'password' => [
            'type' => 'password',
            'label' => 'Password',
            'required' => true,
            'placeholder' => 'Enter password',
            'help' => 'Password for the Veeam ONE user account.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 3600, // 1 hour
            'help' => 'How often to sync Veeam ONE data. Recommended: 3600 (1 hour).',
        ],
        'sync_historical_days' => [
            'type' => 'number',
            'label' => 'Historical Days',
            'default' => 7,
            'help' => 'Number of days of historical backup sessions to sync.',
        ],
    ],

    'data_types' => [
        'veeam_backup_job' => 'Backup Jobs - Configured backup job definitions and schedules',
        'veeam_backup_session' => 'Backup Sessions - Individual backup job execution results',
        'veeam_alarm' => 'Alarms - Triggered alerts and warnings',
        'veeam_repository' => 'Repositories - Backup storage targets and capacity',
        'veeam_protected_workload' => 'Protected Workloads - VMs and workloads being backed up',
    ],

    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_allowance' => 10,
        'min_request_interval_ms' => 100,
        'concurrent_requests' => 3,
        'historical_import_multiplier' => 0.5,
    ],

    'sync_frequency' => [
        'recommended' => 'hourly',
        'minimum' => 'every_30_minutes',
        'maximum' => 'daily',
    ],

    'historical_data' => [
        'supports_historical' => true,
        'max_range_days' => 365,
        'recommended_range_days' => 30,
    ],

    'features' => [
        'customer_mapping' => false, // Veeam ONE doesn't have multi-tenant customer concept
        'historical_import' => true,
        'real_time_sync' => false,
        'webhooks' => false,
        'dashboard_widgets' => true,
    ],

    'api_info' => [
        'documentation' => 'https://helpcenter.veeam.com/references/one/13/rest/',
        'auth_method' => 'OAuth2 Password Grant',
        'base_path' => '/api',
        'token_endpoint' => '/api/token',
        'token_expiry_minutes' => 15,
        'ssl_note' => 'Self-signed certificates are common; SSL verification is disabled by default.',
    ],
];
