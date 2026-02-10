<?php

declare(strict_types=1);

return [
    'id' => 'threatlocker_summary',
    'name' => 'ThreatLocker Summary Report',
    'description' => 'Application control, endpoint security posture, and policy compliance from ThreatLocker',
    'class' => \App\Reports\ThreatLockerSummary\ThreatLockerSummary::class,
    'category' => 'security',
    'data_types' => [
        'threatlocker_computer',
        'threatlocker_policy',
        'threatlocker_application',
        'threatlocker_storage_policy',
        'threatlocker_storage_device',
        'threatlocker_audit',
    ],
    'required_integrations' => ['ThreatLocker'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'computers', 'label' => 'Endpoint Overview', 'default' => true],
        ['id' => 'policies', 'label' => 'Application Control Policies', 'default' => true],
        ['id' => 'applications', 'label' => 'Managed Applications', 'default' => true],
        ['id' => 'storage', 'label' => 'Storage Control', 'default' => true],
        ['id' => 'audit', 'label' => 'Audit Activity', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Security Recommendations', 'default' => true],
    ],
];
