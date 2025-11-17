<?php

return [
    'name' => 'ActiveDirectory',
    'display_name' => 'Active Directory',
    'description' => 'Integrate with on-premise Active Directory to sync users, security groups, and memberships via PowerShell agent.',
    
    'credentials' => [
        // Active Directory uses an API key (created separately)
        // The agent stores the API key in Windows Credential Manager
        // No credentials needed in the integration config itself
    ],
    
    'configuration' => [
        'domain' => [
            'type' => 'text',
            'label' => 'Active Directory Domain',
            'required' => true,
            'help' => 'Your Active Directory domain (e.g., customer.local)',
        ],
        'sync_interval' => [
            'type' => 'number',
            'label' => 'Agent Sync Interval (minutes)',
            'default' => 240,
            'help' => 'How often the agent syncs data (configured on the Windows Server, shown here for reference)',
        ],
        'include_disabled_users' => [
            'type' => 'boolean',
            'label' => 'Include Disabled Users',
            'default' => false,
            'help' => 'Whether to sync disabled AD user accounts',
        ],
        'exclude_system_groups' => [
            'type' => 'boolean',
            'label' => 'Exclude System Groups',
            'default' => true,
            'help' => 'Exclude built-in Windows system groups from sync',
        ],
    ],
    
    'data_types' => [
        'users' => 'Active Directory Users',
        'security_groups' => 'Security Groups',
        'group_memberships' => 'Group Memberships',
    ],
    
    'rate_limits' => [
        // No rate limits - the agent pushes data to us
        'requests_per_minute' => null,
        'requests_per_hour' => null,
        'burst_allowance' => null,
    ],
    
    'supports_historical' => false, // AD doesn't have historical data - it's current state only
    'historical_range_days' => 0,
    
    'is_agent_based' => true, // Special flag indicating this uses a push agent
    'agent_platform' => 'Windows Server',
    'agent_language' => 'PowerShell',
    
    'documentation_url' => '/docs/integrations/active-directory',
    
    'setup_instructions' => [
        'Create an API key in the MSP Reports Platform',
        'Download the Active Directory agent from /agents/windows_server/active-directory',
        'Copy the agent to your Windows Server (Domain Controller recommended)',
        'Configure config.json with your API URL and company ID',
        'Store the API key securely using install.ps1 -StoreCredentials',
        'Install the agent as a scheduled task: install.ps1 -InstallScheduledTask',
        'Verify sync in Integration dashboard',
    ],
];
