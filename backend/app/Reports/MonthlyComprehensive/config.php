<?php

return [
    'id' => 'monthly_comprehensive',
    'name' => 'Monthly Comprehensive Report',
    'description' => 'Customizable combined report - choose which sections to include',
    'class' => \App\Reports\MonthlyComprehensive\MonthlyComprehensive::class,
    'category' => 'executive',
    'data_types' => ['uptime_metric', 'ticket', 'backup_status', 'security_alert'],
    'required_integrations' => ['multiple'], // Requires multiple integrations to be useful
    'frequencies' => ['monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'file-text',
    'enabled' => true,
    'configurable_sections' => [
        'uptime' => [
            'label' => 'System Uptime',
            'description' => 'Device uptime metrics and availability',
            'required_data_types' => ['uptime_metric'],
            'default_enabled' => true,
        ],
        'tickets' => [
            'label' => 'Support Tickets',
            'description' => 'Ticket summary and resolution metrics',
            'required_data_types' => ['ticket'],
            'default_enabled' => true,
        ],
        'backups' => [
            'label' => 'Backup Status',
            'description' => 'Backup success rates and device status',
            'required_data_types' => ['backup_status'],
            'default_enabled' => true,
        ],
        'security' => [
            'label' => 'Security Alerts',
            'description' => 'Security incidents and threat detection',
            'required_data_types' => ['security_alert'],
            'default_enabled' => true,
        ],
    ],
];
