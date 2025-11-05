<?php

return [
    'name' => 'Monthly Comprehensive Report',
    'description' => 'Complete monthly overview combining uptime, tickets, backups, and security metrics.',
    'required_data' => ['uptime', 'tickets', 'backup_status', 'security_alerts'],
    'optional_data' => ['device_health', 'storage_usage', 'vulnerabilities'],
    'supported_frequencies' => ['monthly'],
    'output_formats' => ['pdf', 'html'],
    'default_format' => 'pdf',
    'metrics' => [
        'overall_health_score' => true,
        'uptime_percentage' => true,
        'tickets_resolved' => true,
        'backup_success_rate' => true,
        'security_incidents' => true,
    ],
];
