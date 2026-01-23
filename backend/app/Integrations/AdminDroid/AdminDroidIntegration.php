<?php

namespace App\Integrations\AdminDroid;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AdminDroidIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected AdminDroidClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new AdminDroidClient($integration->credentials ?? [], $rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        Log::info('AdminDroidIntegration::sync called - not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => ['AdminDroid integration not implemented yet'],
        ];
    }

    public function getCustomers(): array
    {
        // No customer concept defined for AdminDroid yet
        return [];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'data' => [],
            'errors' => ['AdminDroid historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'AdminDroid',
            'description' => 'AdminDroid Microsoft 365 reporting and analytics integration (placeholder)',
            'data_types' => [
                'admindroid_m365_report',
                'admindroid_user_activity',
                'mailbox_report',
                'license_report',
            ],
            'supports_historical' => false,
            'historical_range_days' => 0,
        ];
    }

    public function disconnect(): void
    {
        Log::info('AdminDroidIntegration::disconnect called - nothing to do for placeholder integration', [
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
                'name' => 'api_url',
                'label' => 'API URL',
                'type' => 'text',
                'required' => false,
                'default' => 'https://api.admindroid.com',
                'placeholder' => 'https://api.admindroid.com',
                'help' => 'AdminDroid API base URL',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your AdminDroid API key',
                'help' => 'Your AdminDroid API key from account settings',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('admindroid', 'AdminDroid');
        $builder->description('Microsoft 365 reporting and analytics');
        
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
