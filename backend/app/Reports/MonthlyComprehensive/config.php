<?php

return [
    'id' => 'monthly_comprehensive',
    'name' => 'Monthly Comprehensive Report',
    'description' => 'Complete monthly overview combining uptime, tickets, backups, and security metrics',
    'class' => \App\Reports\MonthlyComprehensive\MonthlyComprehensive::class,
    'category' => 'executive',
    'data_types' => ['uptime_metric', 'ticket_event', 'backup_status', 'security_alert'],
    'frequencies' => ['monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'file-text',
    'enabled' => true,
];
