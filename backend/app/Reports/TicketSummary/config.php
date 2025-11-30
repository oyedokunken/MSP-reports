<?php

return [
    'id' => 'ticket_summary',
    'name' => 'Ticket Summary Report',
    'description' => 'Ticket statistics, response times, and SLA compliance metrics from PSA systems',
    'class' => \App\Reports\TicketSummary\TicketSummaryReport::class,
    'category' => 'service_desk',
    'data_types' => ['ticket_event', 'time_entry'],
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'ticket',
    'enabled' => true,
];
