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
        ['id' => 'executive_summary', 'label' => 'Executive Summary', 'description' => 'A high-level overview of DMARC health and email volume trends.', 'requires' => 'domains, volume_totals', 'default' => true],
        ['id' => 'compliance', 'label' => 'DMARC Compliance', 'description' => 'Detailed breakdown of SPF, DKIM, and DMARC alignment status.', 'requires' => 'domains, sender_statistics', 'default' => true],
        ['id' => 'senders', 'label' => 'Sender Analysis', 'description' => 'Insightful analysis of top email senders and their authentication status.', 'requires' => 'sender_statistics', 'default' => true],
        ['id' => 'volume_timeline', 'label' => 'Email Volume Timeline', 'description' => 'Visual representation of email traffic over the reporting period.', 'requires' => 'volume_timeline', 'default' => true],
        ['id' => 'ip_sources', 'label' => 'Source IP Analysis', 'description' => 'Reputation and volume metrics for sending IP addresses.', 'requires' => 'ip_address_statistics', 'default' => true],
        ['id' => 'recommendations', 'label' => 'Recommendations', 'description' => 'Actionable insights to improve DMARC policy and security posture.', 'requires' => 'domains, sender_statistics', 'default' => true],
    ],
];
