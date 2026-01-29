<?php

declare(strict_types=1);

return [
    'id' => 'monthly_comprehensive',
    'name' => 'Monthly Comprehensive Report',
    'description' => 'Dynamic comprehensive report that automatically includes all available monthly summaries from customer\'s active integrations',
    'class' => \App\Reports\MonthlyComprehensive\MonthlyComprehensive::class,
    'category' => 'executive',
    'data_types' => [], // Dynamic - depends on customer's integrations
    'required_integrations' => ['multiple'], // Requires multiple integrations to be useful
    'frequencies' => ['daily', 'weekly', 'monthly', 'yearly', 'on-demand'],
    'formats' => ['pdf', 'html'],
    'default_frequency' => 'monthly',
    'default_format' => 'pdf',
    'icon' => 'file-text',
    'enabled' => true,
    'dynamic_sections' => true, // Indicates sections are determined at runtime
    'included_report_types' => [
        'standard' => [
            'uptime_monthly' => 'System Uptime Report',
            'backup_status' => 'Backup Status Report',
            'security_summary' => 'Security Summary Report',
        ],
        'integration_summaries' => [
            'autotask_monthly_summary' => 'Autotask Monthly Summary',
            'datto_rmm_monthly_summary' => 'Datto RMM Monthly Summary',
            'bitdefender_monthly_summary' => 'BitDefender Monthly Summary',
            'itglue_monthly_summary' => 'ITGlue Monthly Summary',
            'saas_monthly_summary' => 'SaaS Alerts Monthly Summary',
            'sendmarc_monthly_summary' => 'Sendmarc Monthly Summary',
        ],
    ],
];
