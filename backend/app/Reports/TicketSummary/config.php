<?php

return [
    'name' => 'Ticket Summary Report',
    'description' => 'Shows ticket statistics, response times, and SLA compliance for the reporting period.',
    'required_data' => ['tickets', 'ticket_events'],
    'optional_data' => ['time_entries', 'sla_metrics'],
    'supported_frequencies' => ['weekly', 'monthly'],
    'output_formats' => ['pdf', 'html', 'csv'],
    'default_format' => 'pdf',
    'metrics' => [
        'tickets_opened' => true,
        'tickets_closed' => true,
        'avg_response_time' => true,
        'sla_compliance' => true,
        'ticket_backlog' => true,
    ],
];
