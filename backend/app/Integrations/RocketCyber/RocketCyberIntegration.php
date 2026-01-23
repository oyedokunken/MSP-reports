<?php

namespace App\Integrations\RocketCyber;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RocketCyberIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected RocketCyberClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new RocketCyberClient($integration->credentials ?? [], $rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        Log::info('RocketCyberIntegration::sync called - not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => ['RocketCyber integration not implemented yet'],
        ];
    }

    public function getCustomers(): array
    {
        // No customer concept defined for RocketCyber yet
        return [];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'data' => [],
            'errors' => ['RocketCyber historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'RocketCyber',
            'description' => 'RocketCyber security monitoring and threat detection integration (placeholder)',
            'data_types' => [
                'rocketcyber_security_incident',
                'rocketcyber_threat_detection',
                'security_alert',
                'endpoint_event',
            ],
            'supports_historical' => false,
            'historical_range_days' => 0,
        ];
    }

    public function disconnect(): void
    {
        Log::info('RocketCyberIntegration::disconnect called - nothing to do for placeholder integration', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your RocketCyber API key',
                'help' => 'Your RocketCyber API key from the partner portal',
            ],
            [
                'name' => 'account_id',
                'label' => 'Account ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your RocketCyber Account ID',
                'help' => 'Your RocketCyber account identifier',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('rocketcyber', 'RocketCyber');
        $builder->description('Security monitoring and threat detection');
        
        $builder->addSection('status', 'Integration Status', 'grid')
            ->icon('Shield')
            ->dataPath('status')
            ->gridColumns(2)
            ->addMetric('status', 'Status', 'status', 'text', 'CheckCircle', 'blue')
            ->addMetric('message', 'Message', 'message', 'text', 'Info', 'gray')
            ->end();
        
        return $builder->build();
    }
}
