<?php

namespace App\Console\Commands\Integrations;

use Illuminate\Console\Command;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\IntegrationCustomer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VerifyDataFilter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:verify-filter 
                            {customer_id : The ID of the customer} 
                            {--frequency=monthly : The frequency to test (daily, weekly, monthly, yearly, all)} 
                            {--type= : Optional data type filter (e.g. ticket, alert)}
                            {--limit=20 : Number of records to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify frequency filtering for integration data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customerId = $this->argument('customer_id');
        $frequency = $this->option('frequency');
        $dataType = $this->option('type');
        $limit = (int) $this->option('limit');

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("Verifying data for: {$customer->canonical_name} (ID: {$customerId})");
        $this->info("Frequency: " . ($frequency ?: 'all'));

        // Look up integration customers for this customer via pivot
        $icIds = DB::table('customer_mappings')
            ->where('customer_id', $customerId)
            ->pluck('integration_customer_id')
            ->toArray();

        if (empty($icIds)) {
            $this->warn("No integration customers mapped for this customer.");
            return 0;
        }

        // Map frequency to days
        $frequencyDays = [
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'yearly' => 365,
        ];

        $query = IntegrationData::whereIn('integration_customer_id', $icIds)
            ->with(['integration:integration_id,integration_type']);

        if ($dataType) {
            $query->where('data_type', $dataType);
        }

        if (isset($frequencyDays[$frequency])) {
            $days = $frequencyDays[$frequency];
            $cutoff = now()->subDays($days);
            $query->where('data_timestamp', '>=', $cutoff);
            $this->comment("Filtering records where data_timestamp >= " . $cutoff->toDateTimeString() . " ({$days} days ago)");
        } else {
            $this->comment("Showing all records (no time filter)");
        }

        $totalCount = (clone $query)->count();
        $records = $query->orderByDesc('data_timestamp')
            ->limit($limit)
            ->get();

        if ($records->isEmpty()) {
            $this->warn("No records found matching the criteria.");
            return 0;
        }

        $this->info("Found {$totalCount} total records. Showing latest {$records->count()}:");

        $tableData = $records->map(function ($record) {
            // Extract a snippet of the payload for display
            $payload = $record->data_payload;
            $snippet = '';
            
            // Try to find a meaningful field to show
            $fields = ['title', 'name', 'hostname', 'message', 'subject', 'summary'];
            foreach ($fields as $f) {
                if (isset($payload[$f])) {
                    $snippet = $payload[$f];
                    break;
                }
            }

            if (!$snippet && !empty($payload)) {
                $firstKey = array_key_first($payload);
                $snippet = $firstKey . ': ' . (is_array($payload[$firstKey]) ? json_encode($payload[$firstKey]) : $payload[$firstKey]);
            }

            return [
                'ID' => $record->integration_data_id,
                'Type' => $record->data_type,
                'Integration' => $record->integration->integration_type ?? 'N/A',
                'Timestamp' => $record->data_timestamp->toDateTimeString(),
                'Imported' => $record->imported_at ? $record->imported_at->diffForHumans() : 'N/A',
                'Snippet' => substr((string)$snippet, 0, 50),
            ];
        });

        $this->table(
            ['ID', 'Type', 'Integration', 'Timestamp', 'Imported', 'Snippet'],
            $tableData->toArray()
        );

        // Show a summary of counts by type
        $counts = IntegrationData::whereIn('integration_customer_id', $icIds);
        if (isset($frequencyDays[$frequency])) {
            $counts->where('data_timestamp', '>=', now()->subDays($frequencyDays[$frequency]));
        }
        
        $typeCounts = $counts->select('data_type', DB::raw('count(*) as total'))
            ->groupBy('data_type')
            ->get();

        $this->info("\nSummary by Data Type:");
        foreach ($typeCounts as $tc) {
            $this->line("- <info>{$tc->data_type}</info>: {$tc->total} records");
        }

        return 0;
    }
}
