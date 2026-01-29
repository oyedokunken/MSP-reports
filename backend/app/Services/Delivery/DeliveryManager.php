<?php

namespace App\Services\Delivery;

use App\Models\GeneratedReport;
use App\Models\Customer;
use App\Models\Company;
use App\Services\Delivery\Contracts\DeliveryDriverInterface;
use Illuminate\Support\Facades\Log;

class DeliveryManager
{
    /** @var array<string, DeliveryDriverInterface> */
    protected array $drivers = [];

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register the default delivery drivers
     */
    protected function registerDefaultDrivers(): void
    {
        $this->register(new Drivers\EmailDriver());
        $this->register(new Drivers\SharePointDriver());
    }

    /**
     * Register a delivery driver
     */
    public function register(DeliveryDriverInterface $driver): void
    {
        $this->drivers[$driver->getIdentifier()] = $driver;
    }

    /**
     * Get a specific driver by identifier
     */
    public function driver(string $identifier): ?DeliveryDriverInterface
    {
        return $this->drivers[$identifier] ?? null;
    }

    /**
     * Get all registered drivers
     * 
     * @return array<string, DeliveryDriverInterface>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get available delivery methods with their schemas
     * 
     * @return array<string, array{identifier: string, display_name: string, config_schema: array<string, mixed>}>
     */
    public function getAvailableMethods(): array
    {
        $methods = [];
        foreach ($this->drivers as $identifier => $driver) {
            $methods[$identifier] = [
                'identifier' => $identifier,
                'display_name' => $driver->getDisplayName(),
                'config_schema' => $driver->getConfigSchema(),
            ];
        }
        return $methods;
    }

    /**
     * Deliver a report using the configured delivery methods
     *
     * @param GeneratedReport $report
     * @param Customer $customer
     * @param Company $company
     * @param array<int, string>|null $overrideRecipients Override recipients (for manual sends)
     * @return array<string, array{success: bool, destination?: string, message?: string}> Results for each delivery method attempted
     */
    public function deliver(
        GeneratedReport $report,
        Customer $customer,
        Company $company,
        ?array $overrideRecipients = null
    ): array {
        $results = [];
        $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
        
        // Get enabled delivery methods
        $enabledMethods = $this->getEnabledMethods($deliveryConfig);
        
        if (empty($enabledMethods)) {
            // Fall back to email if no methods configured
            $enabledMethods = ['email'];
        }

        foreach ($enabledMethods as $method) {
            $driver = $this->driver($method);
            
            if (!$driver) {
                Log::warning("Delivery driver not found: {$method}", [
                    'report_id' => $report->generated_report_id,
                    'customer_id' => $customer->customer_id,
                ]);
                $results[$method] = [
                    'success' => false,
                    'message' => "Delivery method '{$method}' not available",
                ];
                continue;
            }

            $methodConfig = $deliveryConfig[$method] ?? [];
            
            // Legacy support: if method config is empty but root config matches this method
            if (empty($methodConfig) && ($deliveryConfig['method'] ?? null) === $method) {
                $methodConfig = $deliveryConfig;
            }

            // For email, handle override recipients
            if ($method === 'email' && $overrideRecipients !== null) {
                $methodConfig['override_recipients'] = $overrideRecipients;
            }

            try {
                $results[$method] = $driver->deliver($report, $methodConfig, $company, $customer);
                
                Log::info("Report delivered via {$method}", [
                    'report_id' => $report->generated_report_id,
                    'customer_id' => $customer->customer_id,
                    'success' => $results[$method]['success'],
                ]);
            } catch (\Exception $e) {
                Log::error("Delivery failed via {$method}", [
                    'report_id' => $report->generated_report_id,
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ]);
                
                $results[$method] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get enabled delivery methods from config
     * 
     * @param array<string, mixed> $deliveryConfig
     * @return array<int, string>
     */
    protected function getEnabledMethods(array $deliveryConfig): array
    {
        // Check for explicit enabled_methods array
        if (isset($deliveryConfig['enabled_methods']) && is_array($deliveryConfig['enabled_methods'])) {
            return $deliveryConfig['enabled_methods'];
        }

        // Legacy support: check for 'method' field
        if (isset($deliveryConfig['method'])) {
            return [$deliveryConfig['method']];
        }

        // Check which methods have configuration
        $methods = [];
        foreach ($this->drivers as $identifier => $driver) {
            if (isset($deliveryConfig[$identifier]) && !empty($deliveryConfig[$identifier])) {
                $methods[] = $identifier;
            }
        }

        return $methods;
    }

    /**
     * Validate delivery configuration for a customer
     * 
     * @param array<string, mixed> $deliveryConfig
     * @return array{valid: bool, errors: array<string, array<int, string>>}
     */
    public function validateConfig(array $deliveryConfig): array
    {
        $errors = [];
        $enabledMethods = $this->getEnabledMethods($deliveryConfig);

        foreach ($enabledMethods as $method) {
            $driver = $this->driver($method);
            if (!$driver) {
                $errors[$method] = ["Unknown delivery method: {$method}"];
                continue;
            }

            $methodConfig = $deliveryConfig[$method] ?? [];
            $validation = $driver->validateConfig($methodConfig);
            
            if (!$validation['valid']) {
                $errors[$method] = $validation['errors'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Test a specific delivery method configuration
     * 
     * @param string $method
     * @param array<string, mixed> $config
     * @param Company $company
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $method, array $config, Company $company): array
    {
        $driver = $this->driver($method);
        
        if (!$driver) {
            return [
                'success' => false,
                'message' => "Unknown delivery method: {$method}",
            ];
        }

        return $driver->testConnection($config, $company);
    }
}
