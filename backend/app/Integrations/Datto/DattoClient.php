<?php

namespace App\Integrations\Datto;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DattoClient
{
    protected array $credentials;
    protected string $baseUrl;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        $this->baseUrl = $credentials['base_url'] ?? 'https://api.datto.com';
    }

    /**
     * Test API connection.
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/v1/account');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all customers from Datto.
     */
    public function getCustomers(): array
    {
        $response = $this->makeRequest('GET', '/v1/customers');

        if ($response->successful()) {
            return $response->json('data', []);
        }

        throw new \Exception("Failed to fetch customers: " . $response->body());
    }

    /**
     * Get backup history for a date range.
     */
    public function getBackupHistory(Carbon $startDate, Carbon $endDate): array
    {
        $response = $this->makeRequest('GET', '/v1/backups/history', [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        if ($response->successful()) {
            return $response->json('data', []);
        }

        throw new \Exception("Failed to fetch backup history: " . $response->body());
    }

    /**
     * Make an authenticated API request.
     */
    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        $url = $this->baseUrl . $endpoint;

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->credentials['api_key'],
            'Accept' => 'application/json',
        ])->$method($url, $params);
    }
}
