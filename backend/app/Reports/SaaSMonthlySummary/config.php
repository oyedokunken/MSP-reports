<?php

return [
    'id' => 'saas_monthly_summary',
    'name' => 'SaaS Alerts Monthly Security Report',
    'description' => 'Comprehensive security analysis including user activity, threat detection, geographic access patterns, and application usage',
    'class' => \App\Reports\SaaSMonthlySummary\SaaSMonthlySummary::class,
    'category' => 'security',
    'data_types' => ['saas_security_event'],
    'required_integrations' => ['SaaS'],
    'frequencies' => ['monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
];
