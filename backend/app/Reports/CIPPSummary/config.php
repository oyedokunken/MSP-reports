<?php

declare(strict_types=1);

return [
    'id' => 'cipp_summary',
    'name' => 'Microsoft 365 Summary Report',
    'description' => 'Comprehensive Microsoft 365 tenant report including user & MFA status, license utilization, security alerts, Defender status, conditional access policies, and email protection',
    'class' => \App\Reports\CIPPSummary\CIPPSummaryReport::class,
    'category' => 'cloud',
    'data_types' => ['user', 'license', 'security_alert', 'security_incident', 'conditional_access', 'defender_state', 'mailbox_usage', 'exchange_protection'],
    'required_integrations' => ['CIPP'],
    'frequencies' => ['weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'cloud',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'user_identity', 'label' => 'Users & Identity (MFA)', 'default' => true],
        ['id' => 'license_utilization', 'label' => 'License Utilization', 'default' => true],
        ['id' => 'security_overview', 'label' => 'Security Alerts & Incidents', 'default' => true],
        ['id' => 'defender_status', 'label' => 'Defender Status', 'default' => true],
        ['id' => 'conditional_access', 'label' => 'Conditional Access Policies', 'default' => true],
        ['id' => 'email_protection', 'label' => 'Email & Exchange Protection', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Recommendations', 'default' => true],
    ],
];
