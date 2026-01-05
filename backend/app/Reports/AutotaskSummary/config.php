<?php

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
];
