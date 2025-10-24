<?php

/**
 * Manually trigger auto-match for unmapped Datto customers
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IntegrationCustomer;
use App\Jobs\AutoMatchCustomersJob;

echo "\n🔄 Re-triggering Auto-Match for Datto Customers\n";
echo str_repeat("=", 70) . "\n\n";

// Get unmapped Datto customers
$dattoCustomers = IntegrationCustomer::whereHas('integration', function($q) {
    $q->where('integration_type', 'Datto');
})
->whereNull('customer_id')
->get();

echo "Found {$dattoCustomers->count()} unmapped Datto customers\n\n";

foreach ($dattoCustomers as $customer) {
    echo "Processing: {$customer->name} (ID: {$customer->integration_customer_id})\n";
    
    // Dispatch the job (will be processed by queue workers)
    AutoMatchCustomersJob::dispatch($customer);
    
    echo "  ✅ Job dispatched to queue\n\n";
}

echo str_repeat("=", 70) . "\n";
echo "✅ Jobs dispatched! Queue workers will process them automatically.\n";
echo "   Check status in a few seconds with: GET /api/customers/unmapped\n\n";
