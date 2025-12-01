<?php

return [
    'id' => 'backup_status',
    'name' => 'Backup Status Report',
    'description' => 'Backup success rates, failures, and storage metrics from backup solutions',
    'class' => \App\Reports\BackupStatus\BackupStatus::class,
    'category' => 'backup',
    'data_types' => ['backup_status'],
    'required_integrations' => ['Datto', 'Veeam'], // Backup solutions
    'frequencies' => ['daily', 'weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'database',
    'enabled' => true,
];
