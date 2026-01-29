<?php

namespace App\Services;

use App\Reports\Contracts\ReportInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ReportRegistry
{
    protected array $reports = [];

    protected const CACHE_KEY = 'report_registry';
    
    /**
     * Get cache TTL based on environment
     * Development: 0 (no cache)
     * Production: 3600 seconds (1 hour)
     */
    protected function getCacheTtl(): int
    {
        return config('app.env') === 'local' ? 0 : 3600;
    }

    /**
     * Register a report type
     */
    public function register(string $reportId, array $config): void
    {
        $this->reports[$reportId] = $config;
    }

    /**
     * Get a report instance by ID
     */
    public function get(string $reportId, int $companyId): ?ReportInterface
    {
        $config = $this->reports[$reportId] ?? null;

        if (!$config || !isset($config['class'])) {
            return null;
        }

        $className = $config['class'];

        if (!class_exists($className)) {
            Log::warning('Report class not found', [
                'report_id' => $reportId,
                'class' => $className,
            ]);
            return null;
        }

        try {
            return new $className($companyId);
        } catch (\Exception $e) {
            Log::error('Failed to instantiate report', [
                'report_id' => $reportId,
                'class' => $className,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all registered reports
     */
    public function all(): array
    {
        return $this->reports;
    }

    /**
     * Get available reports (with metadata)
     */
    public function getAvailable(int $companyId): array
    {
        // In development, always re-discover to pick up new report types
        if (config('app.env') === 'local') {
            $this->discover();
        }

        $available = [];

        foreach ($this->reports as $reportId => $config) {
            // Skip disabled reports
            if (isset($config['enabled']) && $config['enabled'] === false) {
                continue;
            }

            $report = $this->get($reportId, $companyId);

            if ($report) {
                $metadata = $report->getMetadata();
                $available[] = array_merge($config, $metadata);
            }
        }

        return $available;
    }

    /**
     * Check if a report type exists
     */
    public function has(string $reportId): bool
    {
        return isset($this->reports[$reportId]);
    }

    /**
     * Get reports by category
     */
    public function getByCategory(string $category, int $companyId): array
    {
        return array_filter($this->getAvailable($companyId), function ($report) use ($category) {
            return ($report['category'] ?? '') === $category;
        });
    }

    /**
     * Get reports that support a specific frequency
     */
    public function getByFrequency(string $frequency, int $companyId): array
    {
        return array_filter($this->getAvailable($companyId), function ($report) use ($frequency) {
            return in_array($frequency, $report['frequencies'] ?? []);
        });
    }

    /**
     * Auto-discover reports from the Reports directory
     */
    public function discover(): void
    {
        // In development, skip cache entirely and always discover fresh
        $isDevelopment = config('app.env') === 'local';
        
        // Check cache first (only in production)
        if (!$isDevelopment) {
            try {
                $cached = Cache::get(self::CACHE_KEY);
                if ($cached !== null) {
                    $this->reports = $cached;
                    return;
                }
            } catch (\Exception $e) {
                // Cache might not be available during migration or setup
                Log::warning('ReportRegistry cache unavailable: ' . $e->getMessage());
            }
        }

        $this->reports = [];
        $reportsPath = app_path('Reports');

        if (!File::exists($reportsPath)) {
            Log::warning('Reports directory not found', ['path' => $reportsPath]);
            return;
        }

        // Get all subdirectories in Reports/
        $directories = File::directories($reportsPath);

        foreach ($directories as $directory) {
            // Skip Contracts directory
            if (basename($directory) === 'Contracts') {
                continue;
            }

            $configPath = $directory . '/config.php';

            if (File::exists($configPath)) {
                try {
                    $config = require $configPath;

                    if (is_array($config)) {
                        // Support both single config and multiple configs
                        if (isset($config['id'])) {
                            // Single report config
                            $this->register($config['id'], $config);
                        } else {
                            // Multiple report configs
                            foreach ($config as $reportId => $reportConfig) {
                                if (is_array($reportConfig)) {
                                    $this->register($reportId, $reportConfig);
                                }
                            }
                        }

                        Log::info('Discovered report config', [
                            'directory' => basename($directory),
                            'config_path' => $configPath,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to load report config', [
                        'config_path' => $configPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Cache the discovered reports
        try {
            $ttl = $this->getCacheTtl();
            if ($ttl > 0) {
                Cache::put(self::CACHE_KEY, $this->reports, $ttl);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cache report registry: ' . $e->getMessage());
        }

        Log::info('Report discovery completed', [
            'total_reports' => count($this->reports),
            'report_ids' => array_keys($this->reports),
        ]);
    }

    /**
     * Clear the registry cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->reports = [];
    }

    /**
     * Get report configuration
     */
    public function getConfig(string $reportId): ?array
    {
        return $this->reports[$reportId] ?? null;
    }

    /**
     * Get count of registered reports
     */
    public function count(): int
    {
        return count($this->reports);
    }
}
