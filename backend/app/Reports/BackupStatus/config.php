<?php

return [
    'name' => 'Backup Status Report',
    'description' => 'Shows backup success rates, failures, and storage usage for the reporting period.',
    'required_data' => ['backup_status', 'backup_jobs'],
    'optional_data' => ['storage_usage', 'backup_alerts'],
    'supported_frequencies' => ['daily', 'weekly', 'monthly'],
    'output_formats' => ['pdf', 'html'],
    'default_format' => 'pdf',
    'metrics' => [
        'success_rate' => true,
        'failed_backups' => true,
        'storage_used' => true,
        'backup_duration' => true,
    ],
];
