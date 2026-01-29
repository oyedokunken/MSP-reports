<?php

declare(strict_types=1);

return [
    'id' => 'autotask_summary',
    'name' => 'Autotask Summary',
    'description' => 'Comprehensive monthly report combining tickets, time entries, technician performance, and contracts from Autotask PSA',
    'class' => \App\Reports\AutotaskSummary\AutotaskSummary::class,
    'category' => 'service_desk',
    'data_types' => ['ticket', 'time_entry', 'resource', 'contract'],
    'required_integrations' => ['Autotask'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'chart-bar',
    'enabled' => true,
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'tickets', 'label' => 'Ticket Summary', 'default' => true],
        ['id' => 'time_entries', 'label' => 'Time Entries', 'default' => true],
        ['id' => 'technicians', 'label' => 'Technician Performance', 'default' => true],
        ['id' => 'contracts', 'label' => 'Contract Status', 'default' => true],
        ['id' => 'alerts', 'label' => 'Alerts & Notifications', 'default' => true],
    ],
];
