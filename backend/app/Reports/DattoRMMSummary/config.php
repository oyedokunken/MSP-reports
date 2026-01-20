<?php

return [
    'id' => 'datto_rmm_summary',
    'name' => 'Datto RMM Summary',
    'description' => 'Comprehensive monthly report covering device health, alerts, compliance, security, and uptime metrics from Datto RMM',
    'class' => \App\Reports\DattoRMMSummary\DattoRMMSummary::class,
    'category' => 'infrastructure',
    'data_types' => ['device_status', 'alert', 'uptime_metric'],
    'required_integrations' => ['Datto'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'description' => 'High-level overview of device status and alert activity.', 'requires' => 'device_status, alert', 'default' => true],
        ['id' => 'devices', 'label' => 'Device Health & Status', 'description' => 'Detailed list of managed devices and their current status.', 'requires' => 'device_status', 'default' => true],
        ['id' => 'alerts', 'label' => 'Alerts & Monitoring', 'description' => 'Summary of recent and critical alerts from Datto RMM.', 'requires' => 'alert', 'default' => true],
        ['id' => 'compliance', 'label' => 'Compliance & Patching', 'description' => 'Patch management status and OS distribution.', 'requires' => 'device_status', 'default' => true],
        ['id' => 'security', 'label' => 'Security Status', 'description' => 'Antivirus coverage and basic security metrics.', 'requires' => 'device_status', 'default' => true],
        ['id' => 'uptime', 'label' => 'Uptime Metrics', 'description' => 'Historical uptime tracking for critical infrastructure.', 'requires' => 'uptime_metric', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Recommendations', 'description' => 'Automated insights and suggested actions based on data.', 'requires' => 'device_status, alert', 'default' => true],
    ],
];
