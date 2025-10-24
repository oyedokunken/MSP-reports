<?php

/**
 * Test script to verify Source of Truth auto-create functionality
 * Run with: docker exec reporting-app php tests/test-source-of-truth.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\Customer;

echo "\n🧪 Testing Source of Truth Auto-Create\n";
echo str_repeat("=", 70) . "\n\n";

// Get Autotask integration (should be source of truth)
$autotask = Integration::where('integration_type', 'Autotask')->first();

if (!$autotask) {
    echo "❌ Autotask integration not found. Run seeders first!\n";
    exit(1);
}

echo "✅ Found Autotask Integration\n";
echo "   ID: {$autotask->integration_id}\n";
echo "   Display Name: {$autotask->display_name}\n";
echo "   Source of Truth: " . ($autotask->is_source_of_truth ? 'YES ✅' : 'NO ❌') . "\n\n";

if (!$autotask->is_source_of_truth) {
    echo "⚠️  WARNING: Autotask is NOT marked as source of truth!\n";
    echo "   Setting it now...\n";
    $autotask->is_source_of_truth = true;
    $autotask->save();
    echo "   ✅ Updated!\n\n";
}

// Count customers before
$customersBefore = Customer::where('company_id', $autotask->company_id)->count();
echo "📊 Current State:\n";
echo "   Unified Customers: {$customersBefore}\n\n";

// Create a new customer in Autotask
echo "📋 Test: Creating new customer in Autotask (Source of Truth)\n";
echo str_repeat("-", 70) . "\n";

$testCustomerName = "TestCorp Solutions Pty Ltd " . date('His');
echo "   Customer Name: {$testCustomerName}\n";
echo "   External ID: AT-TEST-" . time() . "\n\n";

echo "   Creating IntegrationCustomer via integration's storeCustomer method...\n";

// Use the integration's storeCustomer method (simulates real sync)
$autotaskIntegration = new \App\Integrations\Autotask\AutotaskIntegration($autotask);

// Use reflection to call the private storeCustomer method
$reflection = new \ReflectionClass($autotaskIntegration);
$method = $reflection->getMethod('storeCustomer');
$method->setAccessible(true);

// Call the method with customer data
$method->invoke($autotaskIntegration, [
    'external_id' => 'AT-TEST-' . time(),
    'name' => $testCustomerName,
    'metadata' => [
        'test' => true,
        'created_at' => now()->toDateTimeString(),
    ],
    'active' => true,
]);

echo "   ✅ storeCustomer() called\n\n";

// Wait a moment for any async processing (jobs)
echo "   Waiting for background jobs to process...\n";
sleep(2);

// Find the created integration customer
$integrationCustomer = IntegrationCustomer::where('integration_id', $autotask->integration_id)
    ->where('name', $testCustomerName)
    ->first();

if (!$integrationCustomer) {
    echo "   ❌ IntegrationCustomer not found!\n";
    exit(1);
}

echo "   ✅ IntegrationCustomer found (ID: {$integrationCustomer->integration_customer_id})\n\n";

// Check if unified customer was auto-created
if ($integrationCustomer->customer_id) {
    echo "🎉 SUCCESS! Unified Customer Auto-Created!\n";
    echo str_repeat("-", 70) . "\n";
    
    $unifiedCustomer = Customer::find($integrationCustomer->customer_id);
    
    echo "   ✅ Unified Customer ID: {$unifiedCustomer->customer_id}\n";
    echo "   ✅ Canonical Name: {$unifiedCustomer->canonical_name}\n";
    echo "   ✅ Status: {$unifiedCustomer->status}\n";
    
    // Check mapping
    $mapping = $integrationCustomer->customerMappings()->first();
    if ($mapping) {
        echo "   ✅ CustomerMapping created:\n";
        echo "      - Type: {$mapping->mapping_type}\n";
        echo "      - Confidence: {$mapping->confidence_score}%\n";
        echo "      - Notes: {$mapping->notes}\n";
    } else {
        echo "   ⚠️  CustomerMapping NOT found (this is a bug!)\n";
    }
    
    echo "\n";
    
    // Count customers after
    $customersAfter = Customer::where('company_id', $autotask->company_id)->count();
    echo "📊 Result:\n";
    echo "   Unified Customers Before: {$customersBefore}\n";
    echo "   Unified Customers After: {$customersAfter}\n";
    echo "   New Customers Created: " . ($customersAfter - $customersBefore) . "\n\n";
    
    // Cleanup
    echo "🧹 Cleaning up test data...\n";
    $mapping?->delete();
    $integrationCustomer->delete();
    $unifiedCustomer->delete();
    echo "   ✅ Cleanup complete\n\n";
    
} else {
    echo "❌ FAILED! Unified Customer NOT Auto-Created\n";
    echo str_repeat("-", 70) . "\n";
    echo "   IntegrationCustomer.customer_id is still NULL\n";
    echo "   This means the source of truth logic didn't trigger!\n\n";
    
    echo "🔍 Debugging Info:\n";
    echo "   Integration ID: {$autotask->integration_id}\n";
    echo "   Is Source of Truth: " . ($autotask->is_source_of_truth ? 'true' : 'false') . "\n";
    echo "   IntegrationCustomer ID: {$integrationCustomer->integration_customer_id}\n";
    echo "   IntegrationCustomer.customer_id: " . ($integrationCustomer->customer_id ?? 'NULL') . "\n\n";
    
    // Cleanup
    echo "🧹 Cleaning up test data...\n";
    $integrationCustomer->delete();
    echo "   ✅ Cleanup complete\n\n";
}

echo str_repeat("=", 70) . "\n";
echo "✅ Test complete!\n\n";
