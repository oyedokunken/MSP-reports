<?php

/**
 * Quick test script to verify auto-mapping functionality
 * Run with: docker exec reporting-app php tests/test-auto-mapping.php
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\AutoMatchCustomersJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Services\CustomerMappingService;

echo "\n🧪 Testing Auto-Mapping Functionality\n";
echo str_repeat('=', 60) . "\n\n";

// Get test company
$company = Company::first();
if (!$company) {
    echo "❌ No company found. Run seeders first!\n";
    exit(1);
}

echo "✅ Company: {$company->name}\n";
echo '   Auto-match enabled: ' . ($company->settings['customer_mapping']['auto_match_enabled'] ? 'YES' : 'NO') . "\n";
echo "   Threshold: {$company->settings['customer_mapping']['auto_match_threshold']}%\n\n";

// Get test integration
$integration = Integration::where('company_id', $company->company_id)->first();
if (!$integration) {
    echo "❌ No integration found. Run seeders first!\n";
    exit(1);
}

echo "✅ Integration: {$integration->display_name}\n\n";

// Get existing customer to match against
$existingCustomer = Customer::where('company_id', $company->company_id)->first();
if (!$existingCustomer) {
    echo "❌ No existing customer found. Run seeders first!\n";
    exit(1);
}

echo "✅ Existing Customer: {$existingCustomer->canonical_name}\n\n";

// Test 1: Create integration customer with similar name (should auto-match)
echo "📋 Test 1: Create exact match with suffix (should auto-match)\n";
echo str_repeat('-', 60) . "\n";

$testName1 = 'ACME Corp';  // Abbreviation of "ACME Corporation"
echo "   Creating: '{$testName1}'\n";
echo "   Should match: '{$existingCustomer->canonical_name}'\n\n";

$integrationCustomer1 = IntegrationCustomer::create([
    'integration_id' => $integration->integration_id,
    'external_id' => 'TEST-001',
    'name' => $testName1,
    'metadata' => ['test' => true],
    'active' => true,
    'last_seen_at' => now(),
]);

// Calculate similarity
$mappingService = new CustomerMappingService();
$similarity = $mappingService->calculateSimilarity($testName1, $existingCustomer->canonical_name);
echo "   Similarity Score: {$similarity}%\n";

if ($similarity >= 85) {
    echo "   ✅ Score >= 85% - Should auto-match!\n";
} else {
    echo "   ⚠️  Score < 85% - Will require manual mapping\n";
}

// Dispatch job
echo "   Dispatching AutoMatchCustomersJob...\n";
AutoMatchCustomersJob::dispatchSync($integrationCustomer1);

// Check if mapped
$integrationCustomer1->refresh();
if ($integrationCustomer1->customer_id) {
    echo "   ✅ SUCCESS! Auto-mapped to customer_id: {$integrationCustomer1->customer_id}\n";

    // Verify mapping record exists
    $mapping = $integrationCustomer1->customerMappings()->first();
    if ($mapping) {
        echo "   ✅ CustomerMapping created: type={$mapping->mapping_type}, confidence={$mapping->confidence_score}%\n";
    } else {
        echo "   ❌ CustomerMapping NOT found!\n";
    }
} else {
    echo "   ❌ FAILED! Not auto-mapped (customer_id is still NULL)\n";
}

echo "\n";

// Test 2: Create integration customer with very different name (should NOT auto-match)
echo "📋 Test 2: Create different name (should NOT auto-match)\n";
echo str_repeat('-', 60) . "\n";

$testName2 = 'Completely Different Company XYZ';
echo "   Creating: '{$testName2}'\n";
echo "   Should match: '{$existingCustomer->canonical_name}'\n\n";

$integrationCustomer2 = IntegrationCustomer::create([
    'integration_id' => $integration->integration_id,
    'external_id' => 'TEST-002',
    'name' => $testName2,
    'metadata' => ['test' => true],
    'active' => true,
    'last_seen_at' => now(),
]);

$similarity2 = $mappingService->calculateSimilarity($testName2, $existingCustomer->canonical_name);
echo "   Similarity Score: {$similarity2}%\n";

if ($similarity2 >= 85) {
    echo "   ⚠️  Score >= 85% - Will auto-match (unexpected!)\n";
} else {
    echo "   ✅ Score < 85% - Should require manual mapping\n";
}

echo "   Dispatching AutoMatchCustomersJob...\n";
AutoMatchCustomersJob::dispatchSync($integrationCustomer2);

$integrationCustomer2->refresh();
if ($integrationCustomer2->customer_id) {
    echo "   ⚠️  Auto-mapped to customer_id: {$integrationCustomer2->customer_id} (unexpected!)\n";
} else {
    echo "   ✅ SUCCESS! Not auto-mapped (requires manual mapping)\n";
}

echo "\n";

// Cleanup
echo "🧹 Cleaning up test data...\n";
$integrationCustomer1->customerMappings()->delete();
$integrationCustomer1->delete();
$integrationCustomer2->delete();
echo "✅ Cleanup complete\n\n";

echo str_repeat('=', 60) . "\n";
echo "✅ Auto-mapping tests complete!\n\n";
