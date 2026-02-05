<?php

declare(strict_types=1);

return [
    'name' => 'CIPP',
    'display_name' => 'CIPP (Microsoft 365)',
    'description' => 'CyberDrain Improved Partner Portal - Comprehensive Microsoft 365 management including security, compliance, identity, and tenant administration.',
    
    'credentials' => [
        'base_url' => [
            'type' => 'text',
            'label' => 'CIPP API URL',
            'required' => true,
            'encrypted' => false,
            'placeholder' => 'https://your-cipp.azurewebsites.net',
            'help' => 'Your CIPP instance URL (e.g., https://cippsm233.azurewebsites.net)',
        ],
        'tenant_id' => [
            'type' => 'text',
            'label' => 'Azure Tenant ID',
            'required' => true,
            'encrypted' => false,
            'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'help' => 'Your Azure AD Tenant ID (GUID)',
        ],
        'client_id' => [
            'type' => 'text',
            'label' => 'Application (Client) ID',
            'required' => true,
            'encrypted' => false,
            'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'help' => 'The Application ID from your CIPP API client registration',
        ],
        'client_secret' => [
            'type' => 'password',
            'label' => 'Client Secret',
            'required' => true,
            'encrypted' => true,
            'help' => 'The client secret from your CIPP API client',
        ],
    ],
    
    'configuration' => [
        'sync_frequency' => [
            'type' => 'select',
            'label' => 'Sync Frequency',
            'options' => [
                '15_minutes' => 'Every 15 Minutes',
                '1_hour' => 'Every Hour (Recommended)',
                '4_hours' => 'Every 4 Hours',
                '12_hours' => 'Every 12 Hours',
                '24_hours' => 'Daily',
            ],
            'default' => '1_hour',
        ],
        'sync_tenants' => [
            'type' => 'checkbox',
            'label' => 'Sync Tenant List',
            'default' => true,
            'help' => 'Sync list of managed M365 tenants as customers',
        ],
        'sync_users' => [
            'type' => 'checkbox',
            'label' => 'Sync Users & MFA Status',
            'default' => true,
            'help' => 'Sync user accounts and MFA configuration',
        ],
        'sync_security' => [
            'type' => 'checkbox',
            'label' => 'Sync Security Alerts',
            'default' => true,
            'help' => 'Sync security alerts and incidents',
        ],
        'sync_licenses' => [
            'type' => 'checkbox',
            'label' => 'Sync License Information',
            'default' => true,
            'help' => 'Sync M365 license assignments and availability',
        ],
        'sync_conditional_access' => [
            'type' => 'checkbox',
            'label' => 'Sync Conditional Access Policies',
            'default' => true,
            'help' => 'Sync conditional access policy configurations',
        ],
        'sync_defender' => [
            'type' => 'checkbox',
            'label' => 'Sync Defender Status',
            'default' => true,
            'help' => 'Sync Microsoft Defender state and vulnerabilities',
        ],
    ],
    
    'data_types' => [
        'tenant' => 'M365 Tenants',
        'user' => 'Users & Identity',
        'mfa_status' => 'MFA Status',
        'license' => 'License Assignments',
        'security_alert' => 'Security Alerts',
        'security_incident' => 'Security Incidents',
        'conditional_access' => 'Conditional Access Policies',
        'defender_state' => 'Defender Status',
        'defender_vulnerability' => 'Defender Vulnerabilities',
        'sign_in' => 'Sign-in Events',
        'audit_log' => 'Audit Logs',
    ],
    
    'supports_historical' => true,
    'historical_range_days' => 30,
    
    'rate_limits' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 500,
        'burst_allowance' => 10,
        'min_request_interval_ms' => 100,
    ],
];
