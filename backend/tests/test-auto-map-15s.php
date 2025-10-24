<?php

/**
 * Test auto-mapping with 15 second wait
 * This demonstrates that customers with ≥85% confidence auto-map automatically
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\Customer;
use App\Services\CustomerMappingService;

echo "\n🧪 Testing Auto-Mapping with 15 Second Wait\n";
echo str_repeat("=", 70) . "\n\n";

// Step 1: Check current unmapped
$unmappedBefore = IntegrationCustomer::whereNull('customer_id')->count();
echo "📊 Before Sync:\n";
echo "   Unmapped customers: {$unmappedBefore}\n\n";

// Step 2: Trigger Datto sync
echo "🔄 Triggering Datto Sync...\n";
$datto = Integration::where('integration_type', 'Datto')->first();
$dattoIntegration = new \App\Integrations\Datto\DattoIntegration($datto);
$result = $dattoIntegration->sync();
echo "   ✅ Synced " . count($result['customers']) . " customers from Datto\n\n";

// Step 3: Check new unmapped customers
$newUnmapped = IntegrationCustomer::whereNull('customer_id')
    ->where('integration_id', $datto->integration_id)
    ->get();

echo "📋 New Unmapped Customers:\n";
$mappingService = app(CustomerMappingService::class);

foreach ($newUnmapped as $ic) {
    $suggestions = $mappingService->getSuggestedMatches($ic, 1);
    $topMatch = $suggestions[0] ?? null;
    
    echo "   - {$ic->name} (ID: {$ic->integration_customer_id})\n";
    if ($topMatch) {
        echo "     → Best match: \"{$topMatch['customer']->canonical_name}\" ({$topMatch['confidence_score']}%)\n";
        if ($topMatch['confidence_score'] >= 85) {
            echo "     ✅ Will auto-map (≥85% threshold)\n";
        } else {
            echo "     ⚠️  Won't auto-map (<85% threshold)\n";
        }
    } else {
        echo "     ❌ No matches found\n";
    }
}

echo "\n";

// Step 4: Wait 15 seconds for queue workers
echo "⏳ Waiting 15 seconds for queue workers to process auto-match jobs...\n";
for ($i = 15; $i > 0; $i--) {
    echo "   {$i} seconds remaining...\r";
    sleep(1);
}
echo "   ✅ Wait complete!                    \n\n";

// Step 5: Check results
echo "📊 After Auto-Matching:\n";
$unmappedAfter = IntegrationCustomer::whereNull('customer_id')->count();
echo "   Unmapped customers: {$unmappedAfter} (was {$unmappedBefore})\n";
echo "   Auto-mapped: " . ($unmappedBefore - $unmappedAfter) . "\n\n";

// Step 6: Show what got auto-mapped
$autoMapped = \App\Models\CustomerMapping::where('mapping_type', 'auto')
    ->with(['integrationCustomer', 'customer'])
    ->whereHas('integrationCustomer', function($q) use ($datto) {
        $q->where('integration_id', $datto->integration_id);
    })
    ->orderBy('mapped_at', 'desc')
    ->limit(5)
    ->get();

if ($autoMapped->count() > 0) {
    echo "✅ Recently Auto-Mapped (from Datto):\n";
    foreach ($autoMapped as $mapping) {
        $timeSince = $mapping->mapped_at->diffForHumans();
        echo "   - \"{$mapping->integrationCustomer->name}\" → \"{$mapping->customer->canonical_name}\"\n";
        echo "     Confidence: {$mapping->confidence_score}% | Mapped: {$timeSince}\n";
    }
} else {
    echo "⚠️  No auto-mappings found (all scores were below 85%)\n";
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Test complete!\n\n";
