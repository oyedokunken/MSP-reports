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
    'available_sections' => [
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
        ['id' => 'compliance', 'label' => 'DMARC Compliance', 'default' => true],
        ['id' => 'senders', 'label' => 'Sender Analysis', 'default' => true],
        ['id' => 'volume_timeline', 'label' => 'Email Volume Timeline', 'default' => true],
        ['id' => 'ip_sources', 'label' => 'Source IP Analysis', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Recommendations', 'default' => true],
    ],
];
