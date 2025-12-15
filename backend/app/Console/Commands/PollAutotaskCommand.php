<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Integrations\Autotask\AutotaskMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PollAutotaskCommand extends Command
{
    protected $signature = 'autotask:poll 
        {--integration= : Specific integration ID to poll}
        {--since= : Start date for polling (default: last 10 minutes)}
        {--until= : End date for polling (default: now)}';

    protected $description = 'Poll Autotask integrations for tickets and time entries';

    public function handle()
    {
        $integrations = $this->getIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('No active Autotask integrations found');
            return 0;
        }

        $since = $this->option('since') 
            ? Carbon::parse($this->option('since')) 
            : now()->subMinutes(10);
            
        $until = $this->option('until') 
            ? Carbon::parse($this->option('until')) 
            : now();

        $this->info("Polling Autotask integrations (from: {$since->toDateTimeString()}, to: {$until->toDateTimeString()})");

        foreach ($integrations as $integration) {
            $this->info("Processing integration: {$integration->name} (ID: {$integration->integration_id})");
            
            try {
                $autotaskIntegration = app(\App\Integrations\Autotask\AutotaskIntegration::class, ['integration' => $integration]);
                
                // Poll tickets and time entries
                $this->pollTicketsAndTimeEntries($autotaskIntegration, $integration, $since, $until);
                
                $this->info("✓ Successfully polled integration: {$integration->name}");
            } catch (\Exception $e) {
                $this->error("Failed to poll integration {$integration->name}: {$e->getMessage()}");
                Log::error('Autotask poll failed', [
                    'integration_id' => $integration->integration_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return 0;
    }

    protected function getIntegrations()
    {
        $query = Integration::where('integration_type', 'Autotask')
            ->where('status', 'active');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
        }

        return $query->get();
    }

    protected function pollTicketsAndTimeEntries($autotaskIntegration, Integration $integration, Carbon $since, Carbon $until)
    {
        // Pre-load customer map
        $customerMap = IntegrationCustomer::where('integration_id', $integration->integration_id)
            ->pluck('integration_customer_id', 'external_id')
            ->toArray();

        $ticketsProcessed = 0;
        $timeEntriesProcessed = 0;

        // Poll tickets
        $this->info("  Fetching tickets...");
        $tickets = $autotaskIntegration->client->getTickets($since, $until);
        
        foreach ($tickets as $ticket) {
            try {
                $companyId = $ticket['companyID'] ?? null;
                $integrationCustomerId = $companyId ? ($customerMap[(string)$companyId] ?? null) : null;
                
                $standardized = AutotaskMapper::mapTicket($ticket);
                $standardized['integration_customer_id'] = $integrationCustomerId;
                
                $timestamp = Carbon::parse($standardized['timestamp'])->format('Y-m-d H:i:s');

                IntegrationData::updateOrCreate(
                    [
                        'integration_id' => $integration->integration_id,
                        'data_type' => $standardized['data_type'],
                        'data_payload->ticket_id' => $standardized['payload']['ticket_id'] ?? null,
                    ],
                    [
                        'data_timestamp' => $timestamp,
                        'company_id' => $integration->company_id,
                        'integration_customer_id' => $integrationCustomerId,
                        'data_payload' => $standardized['payload'],
                        'imported_at' => now(),
                        'data_version' => 1,
                    ]
                );
                
                $ticketsProcessed++;
            } catch (\Exception $e) {
                Log::error('Failed to process ticket', [
                    'integration_id' => $integration->integration_id,
                    'ticket_id' => $ticket['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Poll time entries
        $this->info("  Fetching time entries...");
        $timeEntries = $autotaskIntegration->client->getTimeEntries($since, $until);
        
        foreach ($timeEntries as $entry) {
            try {
                $ticketId = $entry['ticketID'] ?? null;
                $integrationCustomerId = null;
                
                if ($ticketId) {
                    $ticketData = IntegrationData::where('integration_id', $integration->integration_id)
                        ->where('data_type', 'ticket')
                        ->where('data_payload->ticket_id', $ticketId)
                        ->first();
                        
                    if ($ticketData) {
                        $integrationCustomerId = $ticketData->integration_customer_id;
                    }
                }
                
                $standardized = AutotaskMapper::mapTimeEntry($entry);
                $standardized['integration_customer_id'] = $integrationCustomerId;
                
                $timestamp = Carbon::parse($standardized['timestamp'])->format('Y-m-d H:i:s');
                
                IntegrationData::updateOrCreate(
                    [
                        'integration_id' => $integration->integration_id,
                        'data_type' => $standardized['data_type'],
                        'data_payload->time_entry_id' => $standardized['payload']['time_entry_id'] ?? null,
                    ],
                    [
                        'data_timestamp' => $timestamp,
                        'company_id' => $integration->company_id,
                        'integration_customer_id' => $integrationCustomerId,
                        'data_payload' => $standardized['payload'],
                        'imported_at' => now(),
                        'data_version' => 1,
                    ]
                );
                
                $timeEntriesProcessed++;
            } catch (\Exception $e) {
                Log::error('Failed to process time entry', [
                    'integration_id' => $integration->integration_id,
                    'entry_id' => $entry['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("  - Tickets: {$ticketsProcessed}");
        $this->info("  - Time entries: {$timeEntriesProcessed}");
    }
}
