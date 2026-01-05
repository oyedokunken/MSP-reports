<?php

use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Services\ReportTypeRegistry;

/**
 * Test script for ReportTypeRegistry logic
 */

function testRegistry() {
    echo "Starting ReportTypeRegistry Tests...\n\n";

    // 1. Find a customer with integrations
    $customer = Customer::with('integrationCustomers.integration')->first();
    
    if (!$customer) {
        echo "❌ No customer found in database to test with.\n";
        return;
    }

    echo "Testing with Customer: {$customer->canonical_name} (ID: {$customer->customer_id})\n";
    
    $integrations = $customer->integrationCustomers->map(fn($ic) => $ic->integration->integration_type)->unique()->toArray();
    echo "Active Integrations: " . implode(', ', $integrations) . "\n\n";

    // 2. Get available report types
    $available = ReportTypeRegistry::getAvailableForCustomer($customer->customer_id);

    echo "Available Report Types:\n";
    foreach ($available as $rt) {
        echo "- {$rt['name']} ({$rt['type']})\n";
        echo "  Required: " . implode(', ', $rt['required_integrations']) . "\n";
        echo "  Sections: " . count($rt['available_sections']) . "\n";
    }

    echo "\nTest Complete.\n";
}

try {
    testRegistry();
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
