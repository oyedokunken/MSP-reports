<?php

declare(strict_types=1);

namespace App\Integrations\ActiveDirectory;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use Carbon\Carbon;

/**
 * Active Directory Integration
 * 
 * This is an agent-based integration - data is pushed to the platform
 * by a PowerShell agent running on customer Windows Servers.
 * 
 * Unlike other integrations, this doesn't pull data from an API.
 * The agent pushes AD users, groups, and memberships via REST API.
 */
final class ActiveDirectoryIntegration implements IntegrationInterface
{
    protected Integration $integration;
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->config = require __DIR__ . '/config.php';
    }

    public function authenticate(): bool
    {
        // Agent-based integration - no authentication needed from platform side
        // The agent authenticates using customer-scoped API keys
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sync(): array
    {
        // Agent-based integration - data is pushed to us, not pulled
        // This method is not used for AD integration
        return [
            'success' => true,
            'message' => 'Active Directory is an agent-based integration. Data is pushed by the AD agent.',
            'data' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        // AD doesn't have "customers" in the traditional sense
        // Each customer has their own AD agent pushing data
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // AD doesn't support historical data - it's current state only
        return [
            'success' => true,
            'message' => 'Active Directory does not support historical data.',
            'data' => [],
            'total_processed' => 0,
        ];
    }

    /**
     * @return array{start: Carbon|null, end: Carbon|null}
     */
    public function getAvailableHistoryRange(): array
    {
        return [
            'start' => null,
            'end' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->config['display_name'] ?? 'Active Directory',
            'description' => $this->config['description'] ?? 'Integrate with on-premise Active Directory via PowerShell agent.',
            'data_types' => array_keys($this->config['data_types'] ?? []),
            'supports_historical' => $this->config['supports_historical'] ?? false,
            'historical_range_days' => $this->config['historical_range_days'] ?? 0,
            'is_agent_based' => $this->config['is_agent_based'] ?? true,
            'agent_platform' => $this->config['agent_platform'] ?? 'Windows Server',
            'setup_instructions' => $this->config['setup_instructions'] ?? [],
        ];
    }

    public function disconnect(): bool
    {
        // Nothing to disconnect - agent-based
        return true;
    }

    public function healthCheck(): bool
    {
        // For agent-based integrations, we check if we've received data recently
        // This could be enhanced to check last sync time from AD customers
        return true;
    }

    /**
     * @return array<string, int|null>
     */
    public function getRateLimits(): array
    {
        return $this->config['rate_limits'] ?? [
            'requests_per_minute' => null,
            'requests_per_hour' => null,
            'burst_allowance' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        // No credentials needed - agent uses customer-scoped API keys
        // Return an informational field instead
        return [
            [
                'name' => 'info',
                'label' => 'Setup Information',
                'type' => 'info',
                'description' => 'Active Directory uses a PowerShell agent installed on your Windows Server. No credentials are needed here - each customer\'s agent uses their own API key.',
                'required' => false,
            ],
        ];
    }
}
