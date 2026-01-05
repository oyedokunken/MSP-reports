<?php

return [
    'id' => 'bitdefender_summary',
    'name' => 'BitDefender Summary Report',
    'description' => 'Endpoint security overview, policy compliance, and threat analysis from BitDefender',
    'class' => \App\Reports\BitDefenderSummary\BitDefenderSummary::class,
    'category' => 'security',
    'data_types' => ['bitdefender_endpoint', 'bitdefender_threat_detection'],
    'required_integrations' => ['BitDefender'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield',
    'enabled' => true,
];
