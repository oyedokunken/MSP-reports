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
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'endpoints', 'label' => 'Endpoint Status', 'default' => true],
        ['id' => 'policies', 'label' => 'Policy Compliance', 'default' => true],
        ['id' => 'operating_systems', 'label' => 'Operating System Distribution', 'default' => true],
        ['id' => 'threats', 'label' => 'Threat Detections', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Security Recommendations', 'default' => true],
    ],
];
