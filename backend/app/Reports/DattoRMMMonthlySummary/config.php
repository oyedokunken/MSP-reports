<?php

return [
    'id' => 'datto_rmm_monthly_summary',
    'name' => 'Datto RMM Monthly Summary',
    'description' => 'Comprehensive monthly report covering device health, alerts, compliance, security, and uptime metrics from Datto RMM',
    'class' => \App\Reports\DattoRMMMonthlySummary\DattoRMMMonthlySummary::class,
    'category' => 'infrastructure',
    'data_types' => ['device_status', 'alert', 'uptime_metric'],
    'required_integrations' => ['Datto'],
    'frequencies' => ['monthly', 'daily', 'weekly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
];
