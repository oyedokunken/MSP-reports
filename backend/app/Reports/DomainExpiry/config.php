<?php

return [
    'id' => 'domain_expiry',
    'name' => 'Domain Expiry Report',
    'description' => 'Track domain registrations expiring in the next 90 days to prevent business disruption',
    'class' => \App\Reports\DomainExpiry\DomainExpiry::class,
    'category' => 'infrastructure',
    'data_types' => ['domain'],
    'required_integrations' => ['ITGlue'],
    'frequencies' => ['weekly', 'monthly'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'globe',
    'enabled' => true,
];
