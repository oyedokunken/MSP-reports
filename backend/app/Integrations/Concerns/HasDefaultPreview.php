<?php

declare(strict_types=1);

namespace App\Integrations\Concerns;

use App\Integrations\DisplayTemplate\TemplateBuilder;
use App\Models\IntegrationCustomer;
use Illuminate\Support\Facades\Log;

/**
 * Provides default implementation of getDisplayTemplate() and getPreviewData()
 * for integrations that don't have custom preview logic yet.
 */
trait HasDefaultPreview
{
    /**
     * Get a default display template for this integration
     * 
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $integrationName = class_basename(static::class);
        $integrationName = str_replace('Integration', '', $integrationName);
        
        $builder = new TemplateBuilder(strtolower($integrationName), $integrationName);
        $builder->description("{$integrationName} integration data");
        
        // Customers table
        $builder->addSection('customers', 'Customers', 'table')
            ->description('Synced customers from ' . $integrationName)
            ->icon('Users')
            ->dataPath('customers')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Customer Name', 'sortable' => true, 'filterable' => true, 'width' => '300px'],
                ['key' => 'external_id', 'label' => 'External ID', 'sortable' => true, 'filterable' => true],
                [
                    'key' => 'active',
                    'label' => 'Status',
                    'sortable' => true,
                    'filterable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Active' => 'success',
                        'Inactive' => 'error',
                    ],
                ],
                ['key' => 'last_seen', 'label' => 'Last Seen', 'sortable' => true, 'format' => 'datetime'],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->selectable()
            ->exportable()
            ->end();
        
        // Overview metrics
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('BarChart3')
            ->dataPath('metrics')
            ->gridColumns(3)
            ->addMetric('total_customers', 'Total Customers', 'total_customers', 'number', 'Users', 'blue')
            ->addMetric('active_customers', 'Active Customers', 'active_customers', 'number', 'CheckCircle', 'green')
            ->addMetric('last_sync', 'Last Sync', 'last_sync', 'datetime', 'RefreshCw', 'gray')
            ->end();
        
        return $builder->build();
    }

    /**
     * Get default preview data showing synced customers
     * 
     * @return array<string, mixed>
     */
    public function getPreviewData(?int $integrationCustomerId = null): array
    {
        try {
            $customer = null;
            if ($integrationCustomerId) {
                $customer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->where('integration_customer_id', $integrationCustomerId)
                    ->first();
                
                if (!$customer) {
                    return [
                        'success' => false,
                        'error' => 'Customer not found',
                    ];
                }
            }

            // Get synced customers from database
            $query = IntegrationCustomer::where('integration_id', $this->integration->integration_id);
            
            if ($customer) {
                $query->where('integration_customer_id', $customer->integration_customer_id);
            }
            
            $customers = $query->get();
            
            $customersData = $customers->map(function ($customer) {
                return [
                    'id' => $customer->integration_customer_id,
                    'name' => $customer->name,
                    'external_id' => $customer->external_id,
                    'active' => $customer->active ? 'Active' : 'Inactive',
                    'last_seen' => $customer->last_seen_at?->toIso8601String() ?? 'Never',
                ];
            })->toArray();

            // Calculate metrics
            $totalCustomers = $customers->count();
            $activeCustomers = $customers->where('active', true)->count();

            return [
                'success' => true,
                'customer' => $customer ? [
                    'id' => $customer->integration_customer_id,
                    'name' => $customer->name,
                    'external_id' => $customer->external_id,
                ] : null,
                'data' => [
                    'customers' => $customersData,
                    'metrics' => [
                        'total_customers' => $totalCustomers,
                        'active_customers' => $activeCustomers,
                        'last_sync' => $this->integration->last_sync_at?->toIso8601String() ?? 'Never',
                    ],
                ],
                'timestamp' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Integration: Failed to get preview data', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => get_class($this),
                'customer_id' => $integrationCustomerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Default credential fields - override in specific integrations
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your API key',
                'help' => 'API key from your account settings',
            ],
        ];
    }
}
