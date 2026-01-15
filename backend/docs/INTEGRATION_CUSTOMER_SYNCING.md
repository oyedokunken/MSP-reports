# Integration Customer Syncing Guide

## Overview

This guide explains how customer data is synchronized from external integrations into the reporting platform, how customer mapping works, and how to implement customer syncing in new integrations.

## Customer Sync Flow

### High-Level Process

1. **Integration Sync Triggered** - Manual or scheduled sync initiated
2. **Fetch Customers** - Integration pulls customer list from external API
3. **Transform Data** - Mapper converts external format to standardized format
4. **Store Integration Customers** - Raw customer records stored in `integration_customers` table
5. **Auto-Mapping Attempt** - System tries to match to existing unified customers
6. **Manual Mapping** - Unmapped customers flagged for admin review
7. **Unified Customer Record** - Mapped customers linked to canonical `customers` table

### Data Flow Diagram

```
External API (Autotask, Datto, etc.)
    ↓
Integration Client (fetch customers)
    ↓
Integration Mapper (standardize format)
    ↓
IntegrationCustomer Model (store raw data)
    ↓
Customer Mapping Service (auto-match)
    ↓
Customer Model (unified record)
```

## Database Structure

### IntegrationCustomer Table

Stores raw customer data as it exists in the external system:

```php
integration_customers
├── id
├── integration_id          // Which integration this came from
├── external_id             // Customer ID in external system
├── customer_name           // Name as it appears in external system
├── customer_metadata       // JSON - all other customer data
├── active                  // Is this customer still active?
├── last_seen_at           // Last time we saw this in a sync
├── created_at
└── updated_at
```

### Customer Table

Unified customer record across all integrations:

```php
customers
├── id
├── company_id              // Tenant isolation
├── canonical_name          // The "correct" name for this customer
├── status                  // active/inactive
├── report_configuration    // JSON - which reports to generate
├── report_destination      // Where to send reports
├── metadata               // JSON - custom fields
├── created_at
└── updated_at
```

### CustomerMapping Table

Links IntegrationCustomers to unified Customers:

```php
customer_mappings
├── id
├── customer_id             // Links to unified customer
├── integration_customer_id // Links to raw integration customer
├── mapping_type            // auto/manual
├── confidence_score        // 0-100 for auto-matches
├── mapped_by_user_id      // For manual mappings
├── mapped_at
├── notes
├── created_at
└── updated_at
```

## Implementation Guide

### Step 1: Implement getCustomers() Method

Every integration must implement the `getCustomers()` method from `IntegrationInterface`:

```php
public function getCustomers(): array
{
    // 1. Fetch customers from external API
    $rawCustomers = $this->client->getCustomers();
    
    // 2. Transform to standardized format
    $customers = [];
    foreach ($rawCustomers as $rawCustomer) {
        $customers[] = YourIntegrationMapper::mapCustomer($rawCustomer);
    }
    
    // 3. Return standardized array
    return $customers;
}
```

### Step 2: Create Mapper Method

Create a static mapper method to transform external customer data:

```php
// In YourIntegrationMapper.php
public static function mapCustomer(array $externalCustomer): array
{
    return [
        'external_id' => $externalCustomer['id'],
        'customer_name' => $externalCustomer['name'],
        'metadata' => [
            'email' => $externalCustomer['email'] ?? null,
            'phone' => $externalCustomer['phone'] ?? null,
            'address' => $externalCustomer['address'] ?? null,
            'account_number' => $externalCustomer['account_number'] ?? null,
            // Include any other relevant fields
        ],
        'active' => $externalCustomer['status'] === 'active',
    ];
}
```

### Step 3: Sync Process

The sync process is handled automatically by the integration framework:

```php
// In YourIntegration.php sync() method
public function sync(): array
{
    // 1. Get customers from external system
    $customers = $this->getCustomers();
    
    // 2. Process each customer
    foreach ($customers as $customerData) {
        // Find or create IntegrationCustomer
        $integrationCustomer = IntegrationCustomer::updateOrCreate(
            [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $customerData['external_id'],
            ],
            [
                'customer_name' => $customerData['customer_name'],
                'customer_metadata' => $customerData['metadata'],
                'active' => $customerData['active'],
                'last_seen_at' => now(),
            ]
        );
        
        // 3. Attempt auto-mapping if not already mapped
        if (!$integrationCustomer->customer_id) {
            $this->attemptAutoMapping($integrationCustomer);
        }
    }
    
    return [
        'records_synced' => count($customers),
        'customers_mapped' => $mappedCount,
        // ... other stats
    ];
}
```

## Customer Mapping

### Auto-Mapping Algorithm

The system automatically attempts to match integration customers to unified customers:

```php
protected function attemptAutoMapping(IntegrationCustomer $integrationCustomer): void
{
    $companyId = $this->integration->company_id;
    $customerName = $integrationCustomer->customer_name;
    
    // Get all existing customers for this company
    $existingCustomers = Customer::where('company_id', $companyId)->get();
    
    // Calculate similarity scores
    $bestMatch = null;
    $bestScore = 0;
    
    foreach ($existingCustomers as $customer) {
        $score = $this->calculateSimilarity(
            $customerName,
            $customer->canonical_name
        );
        
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $customer;
        }
    }
    
    // Get threshold from company settings (default 85%)
    $threshold = $this->integration->company->settings['customer_mapping']['auto_match_threshold'] ?? 85;
    
    // Auto-map if score meets threshold
    if ($bestScore >= $threshold) {
        CustomerMapping::create([
            'customer_id' => $bestMatch->id,
            'integration_customer_id' => $integrationCustomer->id,
            'mapping_type' => 'auto',
            'confidence_score' => $bestScore,
            'mapped_at' => now(),
        ]);
        
        $integrationCustomer->update(['customer_id' => $bestMatch->id]);
    }
}
```

### Similarity Calculation

Multiple algorithms are used to calculate similarity:

```php
protected function calculateSimilarity(string $name1, string $name2): int
{
    // Normalize names
    $name1 = $this->normalizeName($name1);
    $name2 = $this->normalizeName($name2);
    
    // Exact match
    if ($name1 === $name2) {
        return 100;
    }
    
    // Levenshtein distance (edit distance)
    $levenshtein = levenshtein($name1, $name2);
    $maxLength = max(strlen($name1), strlen($name2));
    $levenshteinScore = (1 - ($levenshtein / $maxLength)) * 100;
    
    // Soundex (phonetic matching)
    $soundexMatch = soundex($name1) === soundex($name2);
    $soundexScore = $soundexMatch ? 20 : 0;
    
    // Word overlap
    $words1 = explode(' ', $name1);
    $words2 = explode(' ', $name2);
    $overlap = count(array_intersect($words1, $words2));
    $wordScore = ($overlap / max(count($words1), count($words2))) * 30;
    
    // Weighted average
    $finalScore = ($levenshteinScore * 0.5) + ($soundexScore * 0.2) + ($wordScore * 0.3);
    
    return (int) round($finalScore);
}

protected function normalizeName(string $name): string
{
    // Convert to lowercase
    $name = strtolower($name);
    
    // Remove common suffixes
    $name = preg_replace('/\s+(pty|ltd|llc|inc|corp|corporation|company|co)\b\.?/i', '', $name);
    
    // Remove punctuation
    $name = preg_replace('/[^\w\s]/', '', $name);
    
    // Remove extra whitespace
    $name = preg_replace('/\s+/', ' ', trim($name));
    
    return $name;
}
```

## Manual Mapping

### Unmapped Customers Queue

Customers that don't auto-map are flagged for manual review:

```php
// Get unmapped customers for a company
$unmappedCustomers = IntegrationCustomer::whereNull('customer_id')
    ->whereHas('integration', function($query) use ($companyId) {
        $query->where('company_id', $companyId);
    })
    ->with('integration')
    ->get();
```

### Manual Mapping UI Flow

1. Admin views unmapped customers list
2. System shows suggested matches with confidence scores
3. Admin can:
   - Accept suggested match
   - Search for different customer
   - Create new unified customer
   - Ignore (mark as not a customer)

### API Endpoint for Manual Mapping

```php
// POST /api/customer-mappings
public function createMapping(Request $request)
{
    $validated = $request->validate([
        'integration_customer_id' => 'required|exists:integration_customers,id',
        'customer_id' => 'nullable|exists:customers,id',
        'action' => 'required|in:map,create,ignore',
        'notes' => 'nullable|string',
    ]);
    
    if ($validated['action'] === 'create') {
        // Create new unified customer
        $customer = Customer::create([
            'company_id' => auth()->user()->company_id,
            'canonical_name' => $request->input('canonical_name'),
            'status' => 'active',
        ]);
        
        $validated['customer_id'] = $customer->id;
    }
    
    if ($validated['action'] === 'map') {
        // Create mapping
        CustomerMapping::create([
            'customer_id' => $validated['customer_id'],
            'integration_customer_id' => $validated['integration_customer_id'],
            'mapping_type' => 'manual',
            'confidence_score' => 100,
            'mapped_by_user_id' => auth()->id(),
            'mapped_at' => now(),
            'notes' => $validated['notes'],
        ]);
        
        // Update integration customer
        IntegrationCustomer::find($validated['integration_customer_id'])
            ->update(['customer_id' => $validated['customer_id']]);
    }
    
    return response()->json(['success' => true]);
}
```

## Source of Truth Integration

### Concept

One integration can be designated as the "source of truth" for customer names:

```php
// In company settings
'integrations' => [
    'source_of_truth_integration_id' => 123,
]
```

### Behavior

When an integration is the source of truth:

1. **New customers from source** → Auto-create unified Customer record
2. **Other integrations** → Map to existing customers from source
3. **Name conflicts** → Source of truth name takes precedence
4. **Customer deletion** → Can cascade or orphan based on policy

### Implementation

```php
protected function syncSourceOfTruthCustomer(IntegrationCustomer $integrationCustomer): void
{
    // Auto-create unified customer for source of truth
    $customer = Customer::firstOrCreate(
        [
            'company_id' => $this->integration->company_id,
            'canonical_name' => $integrationCustomer->customer_name,
        ],
        [
            'status' => $integrationCustomer->active ? 'active' : 'inactive',
            'metadata' => $integrationCustomer->customer_metadata,
        ]
    );
    
    // Create mapping
    CustomerMapping::create([
        'customer_id' => $customer->id,
        'integration_customer_id' => $integrationCustomer->id,
        'mapping_type' => 'auto',
        'confidence_score' => 100,
        'mapped_at' => now(),
        'notes' => 'Auto-created from source of truth integration',
    ]);
    
    $integrationCustomer->update(['customer_id' => $customer->id]);
}
```

## Best Practices

### For Integration Developers

1. **Always return consistent data** - Same customer should have same external_id across syncs
2. **Include all available metadata** - More data = better auto-matching
3. **Mark inactive customers** - Set `active: false` for deleted/inactive customers
4. **Handle pagination** - Large customer lists should be paginated
5. **Log sync operations** - Track what was synced and any errors

### For System Administrators

1. **Review auto-mappings** - Check confidence scores regularly
2. **Adjust threshold** - Lower threshold = more auto-matches (but less accurate)
3. **Designate source of truth** - Choose most authoritative integration
4. **Regular audits** - Periodically review customer mappings
5. **Document exceptions** - Note why certain mappings were made manually

## Troubleshooting

### Customer Not Auto-Mapping

**Possible causes:**
- Name too different (below threshold)
- No existing unified customer to map to
- Integration not syncing customer data

**Solutions:**
- Lower auto-match threshold in company settings
- Manually create unified customer first
- Check integration's `getCustomers()` implementation

### Duplicate Customers Created

**Possible causes:**
- Multiple integrations creating customers
- Source of truth not designated
- Auto-match threshold too low

**Solutions:**
- Use customer merge tool
- Designate source of truth integration
- Increase auto-match threshold

### Wrong Customer Mapped

**Possible causes:**
- Similar names causing false positive
- Auto-match threshold too low

**Solutions:**
- Delete incorrect mapping
- Create correct mapping manually
- Increase auto-match threshold
- Add notes explaining correct mapping

## Example: Complete Integration Implementation

```php
// AutotaskIntegration.php
public function sync(): array
{
    $customers = $this->getCustomers();
    $newCount = 0;
    $mappedCount = 0;
    
    foreach ($customers as $customerData) {
        $integrationCustomer = IntegrationCustomer::updateOrCreate(
            [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $customerData['external_id'],
            ],
            [
                'customer_name' => $customerData['customer_name'],
                'customer_metadata' => $customerData['metadata'],
                'active' => $customerData['active'],
                'last_seen_at' => now(),
            ]
        );
        
        if ($integrationCustomer->wasRecentlyCreated) {
            $newCount++;
        }
        
        // Attempt mapping if not already mapped
        if (!$integrationCustomer->customer_id) {
            if ($this->isSourceOfTruth()) {
                $this->syncSourceOfTruthCustomer($integrationCustomer);
                $mappedCount++;
            } else {
                $mapped = $this->attemptAutoMapping($integrationCustomer);
                if ($mapped) {
                    $mappedCount++;
                }
            }
        }
    }
    
    return [
        'records_synced' => count($customers),
        'records_new' => $newCount,
        'customers_mapped' => $mappedCount,
        'records_failed' => 0,
        'errors' => [],
    ];
}
```

## Summary

Customer syncing in the reporting platform:

1. **Pulls customer data** from external integrations
2. **Stores raw data** in `integration_customers` table
3. **Attempts auto-mapping** using similarity algorithms
4. **Flags unmapped customers** for manual review
5. **Creates unified records** in `customers` table
6. **Supports source of truth** for authoritative customer lists
7. **Provides manual tools** for mapping corrections

This ensures customer data from multiple integrations is unified while maintaining traceability back to the original sources.
