<?php

declare(strict_types=1);

return [
    'id' => 'saas_summary',
    'name' => 'SaaS Alerts Security Report',
    'description' => 'Comprehensive security analysis including user activity, threat detection, geographic access patterns, and application usage',
    'class' => \App\Reports\SaaSSummary\SaaSSummary::class,
    'category' => 'security',
    'data_types' => ['saas_security_event'],
    'required_integrations' => ['SaaS'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'user_activity', 'label' => 'User Activity', 'default' => true],
        ['id' => 'geographic_data', 'label' => 'Geographic Access Patterns', 'default' => true],
        ['id' => 'application_usage', 'label' => 'Application Usage', 'default' => true],
        ['id' => 'threat_statistics', 'label' => 'Threat Statistics', 'default' => true],
        ['id' => 'device_statistics', 'label' => 'Device Statistics', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Security Recommendations', 'default' => true],
    ],
];
