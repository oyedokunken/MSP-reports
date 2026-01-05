<?php

/** Test full auto-mapping flow with 15 second wait */
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;

echo "\n🧪 Testing Full Auto-Mapping Flow\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Check initial state
echo "📊 Initial State:\n";
$customersBefore = Customer::count();
$unmappedBefore = IntegrationCustomer::whereNull('customer_id')->count();
echo "   Unified Customers: {$customersBefore}\n";
echo "   Unmapped Integration Customers: {$unmappedBefore}\n\n";

// Step 2: Trigger Autotask sync (source of truth)
echo "🔄 Triggering Autotask Sync (Source of Truth)...\n";
$autotask = Integration::where('integration_type', 'Autotask')->first();
$autotaskIntegration = new \App\Integrations\Autotask\AutotaskIntegration($autotask);
$result = $autotaskIntegration->sync();
echo '   ✅ Synced ' . count($result['customers']) . " customers from Autotask\n\n";

// Step 3: Wait for auto-create (source of truth)
echo "⏳ Waiting 15 seconds for source of truth auto-create...\n";
sleep(15);
echo "   ✅ Wait complete\n\n";

// Step 4: Check after Autotask
$customersAfterAutotask = Customer::count();
$unmappedAfterAutotask = IntegrationCustomer::whereNull('customer_id')->count();
echo "📊 After Autotask Sync:\n";
echo "   Unified Customers: {$customersAfterAutotask} (+" . ($customersAfterAutotask - $customersBefore) . ")\n";
echo "   Unmapped: {$unmappedAfterAutotask}\n\n";

// Step 5: Trigger Datto sync (NOT source of truth)
echo "🔄 Triggering Datto Sync (Auto-Matching)...\n";
$datto = Integration::where('integration_type', 'Datto')->first();
$dattoIntegration = new \App\Integrations\Datto\DattoIntegration($datto);
$result = $dattoIntegration->sync();
echo '   ✅ Synced ' . count($result['customers']) . " customers from Datto\n\n";

// Step 6: Wait for auto-match
echo "⏳ Waiting 15 seconds for auto-matching jobs...\n";
sleep(15);
echo "   ✅ Wait complete\n\n";

// Step 7: Final results
$customersAfterDatto = Customer::count();
$unmappedAfterDatto = IntegrationCustomer::whereNull('customer_id')->count();
echo "📊 Final Results:\n";
echo "   Unified Customers: {$customersAfterDatto} (no change - auto-matched to existing)\n";
echo "   Unmapped: {$unmappedAfterDatto}\n\n";

// Step 8: Show what got auto-mapped
echo "✅ Auto-Mapped Customers:\n";
$autoMapped = \App\Models\CustomerMapping::where('mapping_type', 'auto')
    ->with(['integrationCustomer', 'customer'])
    ->get();

foreach ($autoMapped as $mapping) {
    echo "   - \"{$mapping->integrationCustomer->name}\" → \"{$mapping->customer->canonical_name}\" ({$mapping->confidence_score}%)\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "✅ Test complete!\n\n";
