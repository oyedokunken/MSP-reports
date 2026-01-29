<?php

declare(strict_types=1);

return [
    'name' => 'ActiveDirectory',
    'display_name' => 'Active Directory',
    'description' => 'Integrate with on-premise Active Directory to sync users, security groups, and memberships via PowerShell agent.',
    
    'credentials' => [
        // Active Directory uses customer-scoped API keys (created separately)
        // The agent stores the API key in Windows Credential Manager
        // No credentials needed in the integration config itself
    ],
    
    'configuration' => [
        // No configuration needed at integration level
        // Settings are per-customer and configured in each agent's config.json
        // Each customer's AD agent is configured independently with their domain, sync options, etc.
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
        'Step 1: Create ONE Active Directory integration for your company (this step - only do once)',
        'Step 2: For each customer, create a customer-scoped API key: php artisan apikey:create --customer-id=X',
        'Step 3: Download the AD agent from /agents/windows_server/active-directory',
        'Step 4: Copy the agent to the customer\'s Windows Server (Domain Controller recommended)',
        'Step 5: Configure config.json with platform API URL and customer\'s API key',
        'Step 6: Store the API key securely: install.ps1 -StoreCredentials',
        'Step 7: Install as scheduled task: install.ps1 -InstallScheduledTask',
        'Step 8: Verify sync in Integration dashboard',
        'Repeat steps 2-8 for each additional customer',
    ],
];
