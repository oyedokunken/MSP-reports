<?php

return [
    'id' => 'ticket_summary',
    'name' => 'Ticket Summary Report',
    'description' => 'Support ticket analysis and technician activity from PSA systems',
    'class' => \App\Reports\TicketSummary\TicketSummaryReport::class,
    'category' => 'support',
    'data_types' => ['ticket', 'time_entry'],
    'required_integrations' => ['Autotask', 'Kaseya'], // PSA/Ticketing systems
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'ticket',
    'enabled' => true,
];
