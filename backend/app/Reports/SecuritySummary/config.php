<?php

return [
    'id' => 'security_summary',
    'name' => 'Security Summary Report',
    'description' => 'Security alerts, incidents, and threat analysis from security monitoring systems',
    'class' => \App\Reports\SecuritySummary\SecuritySummary::class,
    'category' => 'security',
    'data_types' => ['security_alert', 'security_incident'],
    'required_integrations' => ['Datto', 'BitDefender', 'RocketCyber', 'ThreatLocker', 'Barracuda', 'WatchguardAuthPoint'], // Security tools
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield',
    'enabled' => true,
];
