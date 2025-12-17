<?php

return [
    'id' => 'uptime_monthly',
    'name' => 'Monthly Uptime Report',
    'description' => 'Device uptime metrics and availability tracking across all monitoring systems',
    'class' => \App\Reports\UptimeReport\UptimeReport::class,
    'category' => 'infrastructure',
    'data_types' => ['uptime_metric'],
    'required_integrations' => ['Datto', 'Kaseya'], // RMM/Monitoring systems
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'activity',
    'enabled' => false, // Disabled - functionality covered by integration monthly summaries
];
