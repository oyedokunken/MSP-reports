<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AutoMatchCustomersJob;
use App\Models\IntegrationCustomer;
use Illuminate\Console\Command;

class AutoMatchCustomersCommand extends Command
{
    protected $signature = 'customers:auto-match 
                            {--integration= : Only process customers from specific integration ID}
                            {--limit= : Limit number of customers to process}';

    protected $description = 'Trigger auto-matching for unmapped integration customers';

    public function handle(): int
    {
        $this->info('Starting auto-match process...');

        $query = IntegrationCustomer::whereNull('customer_id')
            ->with('integration');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
            $this->info("Filtering to integration ID: {$integrationId}");
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $this->info("Limiting to {$limit} customers");
        }

        $unmappedCustomers = $query->get();
        $total = $unmappedCustomers->count();

        if ($total === 0) {
            $this->info('No unmapped customers found.');
            return 0;
        }

        $this->info("Found {$total} unmapped customers. Dispatching auto-match jobs...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($unmappedCustomers as $integrationCustomer) {
            AutoMatchCustomersJob::dispatch($integrationCustomer);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully dispatched {$total} auto-match jobs to the queue.");
        $this->info('Jobs will be processed by queue workers. Check progress with: php artisan queue:work');

        return 0;
    }
}
