<?php

return [
    'name' => 'Uptime Report',
    'description' => 'Shows device uptime and downtime trends for the last 30 days.',
    'required_data' => ['uptime', 'device_health'],
    'optional_data' => ['tickets'],
    'supported_frequencies' => ['daily', 'weekly', 'monthly'],
    'output_formats' => ['pdf', 'csv', 'html'],
    'default_format' => 'pdf',
    'metrics' => [
        'avg_uptime' => true,
        'max_downtime' => true,
        'ticket_correlation' => false,
    ],
];
