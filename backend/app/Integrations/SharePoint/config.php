<?php

declare(strict_types=1);

return [
    'name' => 'SharePoint Global',
    'description' => 'Deliver reports to company-wide SharePoint folders.',
    'version' => '1.0',
    'support_url' => 'https://docs.microsoft.com/en-us/graph/api/resources/sharepoint',
    
    // Credentials needed for this integration
    'credential_fields' => [
        [
            'name' => 'client_id',
            'label' => 'Client ID',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Enter Azure AD Client ID',
            'description' => 'The Application (client) ID from Azure App Registration.'
        ],
        [
            'name' => 'client_secret',
            'label' => 'Client Secret',
            'type' => 'password',
            'required' => true,
            'placeholder' => 'Enter Client Secret',
            'description' => 'A valid client secret from "Certificates & secrets".'
        ],
        [
            'name' => 'tenant_id',
            'label' => 'Tenant ID',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Enter Tenant (Directory) ID',
            'description' => 'The Directory (tenant) ID from Azure AD.'
        ],
        [
            'name' => 'site_url',
            'label' => 'SharePoint Site URL',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'https://yourdomain.sharepoint.com/sites/yoursite',
            'description' => 'The full URL of the SharePoint site to store reports in.'
        ]
    ],
    
    // What this integration supports
    'features' => [
        'file_delivery' => true,
        'folder_browsing' => true,
    ],

    // Rate limits (Microsoft Graph API limits)
    'rate_limits' => [
        'requests_per_minute' => 200, 
        'requests_per_hour' => 10000,
    ]
];
