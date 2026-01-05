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
];
