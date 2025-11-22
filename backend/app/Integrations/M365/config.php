<?php

return [
    'name' => 'M365',
    'description' => 'Microsoft 365 audit logs and security integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'tenant_id' => [
            'type' => 'text',
            'label' => 'Tenant ID',
            'required' => false,
            'placeholder' => 'Enter Microsoft 365 Tenant ID (optional for now)',
            'help' => 'Your Microsoft 365 tenant ID (GUID format).',
        ],
        'client_id' => [
            'type' => 'text',
            'label' => 'Client ID',
            'required' => false,
            'placeholder' => 'Enter Application (Client) ID (optional for now)',
            'help' => 'Application (Client) ID from Azure AD app registration.',
        ],
        'client_secret' => [
            'type' => 'password',
            'label' => 'Client Secret',
            'required' => false,
            'placeholder' => 'Enter Client Secret (optional for now)',
            'help' => 'Client secret from Azure AD app registration. Placeholder credential until real API details are defined.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync M365 data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'audit_logs' => 'Microsoft 365 Audit Logs (placeholder)',
        'security_alerts' => 'Microsoft 365 Security Alerts (placeholder)',
        'user_activity' => 'Microsoft 365 User Activity (placeholder)',
        'mailbox_activity' => 'Microsoft 365 Mailbox Activity (placeholder)',
        'sharepoint_activity' => 'Microsoft 365 SharePoint Activity (placeholder)',
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
        'recommended' => 'daily',
        'minimum' => 'daily',
        'maximum' => 'weekly',
    ],

    'historical_data' => [
        'supports_historical' => false,
        'max_range_days' => 0,
        'recommended_range_days' => 0,
    ],

    'features' => [
        'customer_mapping' => false,
        'historical_import' => false,
        'real_time_sync' => false,
        'webhooks' => false,
    ],
];
