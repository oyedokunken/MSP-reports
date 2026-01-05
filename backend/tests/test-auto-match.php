<?php

/** Test auto-matching for Datto customers */
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IntegrationCustomer;
use App\Services\CustomerMappingService;

echo "\n🧪 Testing Auto-Match for Datto Customers\n";
echo str_repeat('=', 70) . "\n\n";

// Get unmapped Datto customers
$dattoCustomers = IntegrationCustomer::whereHas('integration', function ($q) {
    $q->where('integration_type', 'Datto');
})
    ->whereNull('customer_id')
    ->get();

echo "Found {$dattoCustomers->count()} unmapped Datto customers\n\n";

$mappingService = app(CustomerMappingService::class);

// First, show existing customers
$company = $dattoCustomers->first()->integration->company;
$existingCustomers = \App\Models\Customer::where('company_id', $company->company_id)
    ->where('status', 'active')
    ->get();

echo "Existing Customers in Database:\n";
foreach ($existingCustomers as $c) {
    echo "  - {$c->canonical_name} (ID: {$c->customer_id})\n";
}
echo "\n";

foreach ($dattoCustomers as $customer) {
    echo "Testing: {$customer->name} (ID: {$customer->integration_customer_id})\n";
    echo str_repeat('-', 70) . "\n";

    // Calculate similarity manually for debugging
    echo "Similarity scores:\n";
    foreach ($existingCustomers as $existing) {
        $score = $mappingService->calculateSimilarity($customer->name, $existing->canonical_name);
        echo "  vs '{$existing->canonical_name}': {$score}%\n";
    }
    echo "\n";

    $match = $mappingService->autoMatch($customer);

    if ($match) {
        echo "✅ MATCH FOUND!\n";
        echo "   Matched to: {$match['customer']->canonical_name}\n";
        echo "   Confidence: {$match['confidence_score']}%\n";
        echo "   Threshold: 85%\n";

        if ($match['confidence_score'] >= 85) {
            echo "   Status: ✅ Would auto-map\n";
        } else {
            echo "   Status: ⚠️  Below threshold - requires manual mapping\n";
        }
    } else {
        echo "❌ NO MATCH FOUND\n";
        echo "   Requires manual mapping\n";
    }

    echo "\n";
}

echo str_repeat('=', 70) . "\n";
echo "✅ Test complete!\n\n";
