<?php

return [
    'name' => 'Exchange',
    'display_name' => 'Microsoft Exchange Server',
    'description' => 'Integrate with on-premise or cloud Microsoft Exchange Server to sync mailboxes, email aliases, permissions, and usage statistics via PowerShell agent.',
    
    'credentials' => [
        // Exchange uses customer-scoped API keys (created separately)
        // The agent stores the API key in Windows Credential Manager
        // No credentials needed in the integration config itself
    ],
    
    'configuration' => [
        // No configuration needed at integration level
        // Settings are per-customer and configured in each agent's config.json
        // Each customer's Exchange agent is configured independently
    ],
    
    'data_types' => [
        'mailboxes' => 'Exchange Mailboxes',
        'email_aliases' => 'Email Addresses & Aliases',
        'permissions' => 'Mailbox Permissions (FullAccess, SendAs, SendOnBehalf)',
        'statistics' => 'Mailbox Usage Statistics',
    ],
    
    'rate_limits' => [
        // No rate limits - the agent pushes data to us
        'requests_per_minute' => null,
        'requests_per_hour' => null,
        'burst_allowance' => null,
    ],
    
    'supports_historical' => false, // Exchange doesn't have historical data - it's current state only
    'historical_range_days' => 0,
    
    'is_agent_based' => true, // Special flag indicating this uses a push agent
    'agent_platform' => 'Windows Server',
    'agent_language' => 'PowerShell',
    
    'documentation_url' => '/docs/integrations/exchange',
    
    'setup_instructions' => [
        'Step 1: Create ONE Exchange integration for your company (this step - only do once)',
        'Step 2: For each customer, create a customer-scoped API key: php artisan apikey:create --customer-id=X',
        'Step 3: Download the Exchange agent from /agents/windows_server/exchange',
        'Step 4: Copy the agent to the customer\'s Exchange Server',
        'Step 5: Configure config.json with platform API URL and customer\'s API key',
        'Step 6: Store the API key securely: install.ps1 -StoreCredentials',
        'Step 7: Install as scheduled task: install.ps1 -InstallScheduledTask',
        'Step 8: Verify sync in Integration dashboard',
        'Repeat steps 2-8 for each additional customer',
    ],
    
    'features' => [
        'Mailbox inventory and classification',
        'License tracking and optimization',
        'Disk usage and quota monitoring',
        'Email alias management',
        'Permission auditing (FullAccess, SendAs, SendOnBehalf)',
        'Archive mailbox tracking',
        'Litigation hold monitoring',
        'Shared mailbox identification',
        'Resource mailbox tracking (rooms, equipment)',
        'Forwarding rule detection',
        'AD user linkage',
    ],
];
