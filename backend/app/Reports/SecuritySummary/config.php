<?php

return [
    'name' => 'Security Summary Report',
    'description' => 'Weekly security summary including alerts, vulnerabilities, and patch status.',
    'required_data' => ['security_alerts', 'vulnerabilities'],
    'optional_data' => ['patches', 'compliance'],
    'supported_frequencies' => ['weekly', 'monthly'],
    'output_formats' => ['pdf', 'html'],
    'default_format' => 'pdf',
    'metrics' => [
        'critical_alerts' => true,
        'vulnerability_count' => true,
        'patch_compliance' => true,
        'threat_level' => true,
    ],
];
