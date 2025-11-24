<?php

return [
    'name' => 'Kaseya',
    'description' => 'Kaseya GRC (Governance, Risk, and Compliance) integration (placeholder configuration)',
    'version' => '1.0.0',

    // Placeholder credential fields – update once real API details are known
    'credentials' => [
        'api_url' => [
            'type' => 'text',
            'label' => 'API URL',
            'required' => false,
            'default' => 'https://api.kaseya.com',
            'placeholder' => 'https://api.kaseya.com',
            'help' => 'Kaseya GRC API base URL. Use a real endpoint when available.',
        ],
        'api_key' => [
            'type' => 'password',
            'label' => 'API Key',
            'required' => false,
            'placeholder' => 'Enter Kaseya API key (optional for now)',
            'help' => 'Placeholder credential until real Kaseya API details are defined.',
        ],
        'username' => [
            'type' => 'text',
            'label' => 'Username',
            'required' => false,
            'placeholder' => 'Enter Kaseya username (optional for now)',
            'help' => 'Kaseya GRC username.',
        ],
        'password' => [
            'type' => 'password',
            'label' => 'Password',
            'required' => false,
            'placeholder' => 'Enter Kaseya password (optional for now)',
            'help' => 'Kaseya GRC password.',
        ],
    ],

    'configuration' => [
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Sync Interval (seconds)',
            'default' => 86400,
            'help' => 'How often to sync Kaseya data. Placeholder only for now.',
        ],
    ],

    'data_types' => [
        // These are speculative; adjust once real use-cases are known
        'compliance_assessments' => 'Kaseya Compliance Assessments (placeholder)',
        'risk_assessments' => 'Kaseya Risk Assessments (placeholder)',
        'policy_compliance' => 'Kaseya Policy Compliance (placeholder)',
        'audit_reports' => 'Kaseya Audit Reports (placeholder)',
        'vulnerability_assessments' => 'Kaseya Vulnerability Assessments (placeholder)',
        'remediation_tasks' => 'Kaseya Remediation Tasks (placeholder)',
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
