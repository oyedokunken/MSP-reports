<?php

namespace App\Console\Commands\TestReports;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestBarracudaConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'barracuda:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Barracuda API connection and dump request/response details';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info("Starting Barracuda API Test...");

        $clientId = config('services.barracuda.client_id');
        $clientSecret = config('services.barracuda.client_secret');
        $parentAccountId = config('services.barracuda.parent_account_id');
        
        // Use v1 endpoint as discovered in previous testing
        $tokenUrl = 'https://data.barracudanetworks.com/b1/v1/authorization/token'; 

        if (!is_string($clientId) || !is_string($clientSecret)) {
            $this->error("Missing BARRACUDA_CLIENT_ID or BARRACUDA_CLIENT_SECRET in .env");
            return;
        }

        $this->info("\n-------------------------------------------");
        $this->info("STEP 1: Authentication (Get Token)");
        $this->info("-------------------------------------------");
        
        $this->line("Request URL: $tokenUrl");
        $this->line("Request Method: POST");
        $this->line("Request Body (JSON):");
        $json = json_encode([
            'ClientId' => '***' . substr((string)$clientId, -4),
            'ClientSecret' => '***' . substr((string)$clientSecret, -4),
        ], JSON_PRETTY_PRINT);
        $this->line($json ?: '');

        $startTime = microtime(true);
        
        try {
            $response = Http::asJson()->post($tokenUrl, [
                'ClientId' => $clientId,
                'ClientSecret' => $clientSecret,
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->info("\nResponse Status: " . $response->status());
            $this->info("Time Taken: {$duration}ms");
            
            if (!$response->successful()) {
                $this->error("Authentication Failed!");
                $this->line("Response Body:");
                $this->line($response->body());
                return;
            }
            
            $data = $response->json();
            $token = $data['access_token'] ?? null;
            
            if (!$token || !is_string($token)) {
                $this->error("No access token in response");
                return;
            }

            $this->info("Authentication Successful!");
            $this->line("Token received (truncated): " . substr($token, 0, 20) . "...");
            $this->line("Expires in: " . ($data['expires_in'] ?? 'unknown') . " seconds");
            
            if (!$parentAccountId || !is_string($parentAccountId)) {
                $this->error("Missing BARRACUDA_PARENT_ACCOUNT_ID in .env - cannot proceed with account tests");
                return;
            }
            
            $this->testChildAccounts($token, $parentAccountId);
            $this->testProductState($token, $parentAccountId);
            
        } catch (\Exception $e) {
            $this->error("Exception during authentication: " . $e->getMessage());
        }
    }

    private function testChildAccounts(string $token, string $accountId): void
    {
        $this->info("\n-------------------------------------------");
        $this->info("STEP 2: Get Child Accounts");
        $this->info("-------------------------------------------");
        
        $url = "https://data.barracudanetworks.com/b1/v1/accounts/{$accountId}/child-accounts";
        
        $this->line("Request URL: $url");
        $this->line("Request Method: GET");
        $this->line("Headers:");
        $this->line("  Authorization: Bearer " . substr($token, 0, 10) . "...");
        $this->line("  Accept: application/json");
        
        $startTime = microtime(true);
        
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
                
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->info("\nResponse Status: " . $response->status());
            $this->info("Time Taken: {$duration}ms");
            $this->line("Response Body:");
            $this->line($this->formatJson($response->body()));
            
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
    }

    private function testProductState(string $token, string $accountId): void
    {
        $this->info("\n-------------------------------------------");
        $this->info("STEP 3: Get Product State");
        $this->info("-------------------------------------------");
        
        $url = "https://data.barracudanetworks.com/b1/v1/product-state/{$accountId}";
        
        $this->line("Request URL: $url");
        $this->line("Request Method: GET");
        $this->line("Headers:");
        $this->line("  Authorization: Bearer " . substr($token, 0, 10) . "...");
        $this->line("  Accept: application/json");
        
        $startTime = microtime(true);
        
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
                
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->info("\nResponse Status: " . $response->status());
            $this->info("Time Taken: {$duration}ms");
            $this->line("Response Body:");
            $this->line($this->formatJson($response->body()));
            
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
    }

    private function formatJson(mixed $json): string
    {
        $data = json_decode($json);
        if ($data === null) {
            return is_string($json) ? $json : '';
        }
        return (string)json_encode($data, JSON_PRETTY_PRINT);
    }
}
