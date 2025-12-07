<?php

return [
    'id' => 'sendmarc_monthly_summary',
    'name' => 'Sendmarc Monthly Summary Report',
    'description' => 'Monthly DMARC compliance, sender analysis, and source IP insights from Sendmarc',
    'class' => \App\Reports\SendmarcMonthlySummary\SendmarcMonthlySummary::class,
    'category' => 'security',
    'data_types' => ['sender_statistics', 'volume_totals', 'volume_timeline', 'ip_address_statistics'],
    'required_integrations' => ['Sendmarc'],
    'frequencies' => ['monthly', 'quarterly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'mail',
    'enabled' => true,
];
