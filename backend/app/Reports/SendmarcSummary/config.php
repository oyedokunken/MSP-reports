<?php

return [
    'id' => 'sendmarc_summary',
    'name' => 'Sendmarc Summary Report',
    'description' => 'Monthly DMARC compliance, sender analysis, and source IP insights from Sendmarc',
    'class' => \App\Reports\SendmarcSummary\SendmarcSummary::class,
    'category' => 'security',
    'data_types' => ['sender_statistics', 'volume_totals', 'volume_timeline', 'ip_address_statistics'],
    'required_integrations' => ['Sendmarc'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'mail',
    'enabled' => true,
];
