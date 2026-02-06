<?php

namespace App\Console\Commands;

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
    public function handle()
    {
        $this->info("Starting Barracuda API Test...");

        $clientId = env('BARRACUDA_CLIENT_ID');
        $clientSecret = env('BARRACUDA_CLIENT_SECRET');
        $parentAccountId = env('BARRACUDA_PARENT_ACCOUNT_ID');
        
        // Use v1 endpoint as discovered in previous testing
        $tokenUrl = 'https://data.barracudanetworks.com/b1/v1/authorization/token'; 
        // Or use env variable if set, but fallback to known working v1 URL
        // $tokenUrl = env('BARRACUDA_TOKEN_URL', 'https://data.barracudanetworks.com/b1/v1/authorization/token');

        if (empty($clientId) || empty($clientSecret)) {
            $this->error("Missing BARRACUDA_CLIENT_ID or BARRACUDA_CLIENT_SECRET in .env");
            return;
        }

        $this->info("\n-------------------------------------------");
        $this->info("STEP 1: Authentication (Get Token)");
        $this->info("-------------------------------------------");
        
        $this->line("Request URL: $tokenUrl");
        $this->line("Request Method: POST");
        $this->line("Request Body (JSON):");
        $this->line(json_encode([
            'ClientId' => '***' . substr($clientId, -4),
            'ClientSecret' => '***' . substr($clientSecret, -4),
        ], JSON_PRETTY_PRINT));

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
            
            $this->info("Authentication Successful!");
            $this->line("Token received (truncated): " . substr($token, 0, 20) . "...");
            $this->line("Expires in: " . ($data['expires_in'] ?? 'unknown') . " seconds");
            
            if (!$parentAccountId) {
                $this->error("Missing BARRACUDA_PARENT_ACCOUNT_ID in .env - cannot proceed with account tests");
                return;
            }
            
            $this->testChildAccounts($token, $parentAccountId);
            $this->testProductState($token, $parentAccountId);
            
        } catch (\Exception $e) {
            $this->error("Exception during authentication: " . $e->getMessage());
        }
    }

    private function testChildAccounts($token, $accountId)
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

    private function testProductState($token, $accountId)
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

    private function formatJson($json)
    {
        $data = json_decode($json);
        if ($data === null) {
            return $json;
        }
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
