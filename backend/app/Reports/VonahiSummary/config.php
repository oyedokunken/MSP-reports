<?php

declare(strict_types=1);

return [
    'id' => 'vonahi_summary',
    'name' => 'Vonahi vPenTest Summary',
    'description' => 'Automated penetration testing summary including assessment results, security findings, and trend analysis.',
    'class' => \App\Reports\VonahiSummary\VonahiSummary::class,
    'category' => 'security',
    'data_types' => [
        'vonahi_assessment',
        'vonahi_finding',
        'vonahi_report',
        'vonahi_agent',
    ],
    'required_integrations' => ['Vonahi'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'shield-check',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'assessments', 'label' => 'Penetration Test History', 'default' => true],
        ['id' => 'findings', 'label' => 'Security Findings Detail', 'default' => true],
        ['id' => 'agents', 'label' => 'vPenTest Agent Infrastructure', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Security Recommendations', 'default' => true],
    ],
    'options' => [
        'include_findings_list' => [
            'type' => 'boolean',
            'label' => 'Include Detailed Findings List',
            'default' => true,
        ],
        'include_agents_list' => [
            'type' => 'boolean',
            'label' => 'Include Agent Inventory',
            'default' => true,
        ],
        'min_severity_filter' => [
            'type' => 'select',
            'label' => 'Minimum Severity to Show',
            'options' => [
                'informational' => 'Informational',
                'low' => 'Low',
                'medium' => 'Medium',
                'high' => 'High',
                'critical' => 'Critical',
            ],
            'default' => 'informational',
        ],
    ],
];
