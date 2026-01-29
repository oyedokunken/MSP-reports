<?php

namespace App\Services\Delivery\Drivers;

use App\Models\GeneratedReport;
use App\Models\Company;
use App\Models\Customer;
use App\Services\Delivery\Contracts\DeliveryDriverInterface;
use App\Integrations\SharePoint\SharePointIntegration;
use App\Services\ReportNamingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SharePointDriver implements DeliveryDriverInterface
{
    public function getIdentifier(): string
    {
        return 'sharepoint';
    }

    public function getDisplayName(): string
    {
        return 'SharePoint';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigSchema(): array
    {
        return [
            'path' => 'required|string',
            'integration_id' => 'optional|integer', // Optional because we might look it up
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateConfig(array $config): array
    {
        if (empty($config['path'])) {
            return [
                'valid' => false,
                'errors' => ['SharePoint folder path is required.'],
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * @param GeneratedReport $report
     * @param array<string, mixed> $config
     * @param Company $company
     * @param Customer $customer
     * @return array{success: bool, destination: string, message?: string, details?: array<string, mixed>}
     */
    public function deliver(GeneratedReport $report, array $config, Company $company, Customer $customer): array
    {
        try {
            // Find active SharePoint integration for company
            // If integration_id is in config, use it. Otherwise find first valid SharePoint integration
            $integration = null;

            if (!empty($config['integration_id'])) {
                $integration = $company->integrations()->find($config['integration_id']);
            }

            if (!$integration) {
                $integration = $company->integrations()
                    ->where('integration_type', 'SharePoint')
                    ->where('status', 'active')
                    ->first();
            }

            if (!$integration instanceof \App\Models\Integration) {
                throw new \Exception('No active SharePoint integration found for this company.');
            }

            $sharepoint = new SharePointIntegration($integration);
            
            // Get file content
            if (!Storage::disk('reports')->exists($report->file_path)) {
                throw new \Exception("Report file not found: {$report->file_path}");
            }
            
            $content = Storage::disk('reports')->get($report->file_path);
            
            // Use ReportNamingService for consistent filename generation
            $namingService = app(ReportNamingService::class);
            $filename = 'Report.pdf';
            
            if ($report->reportSchedule) {
                $filename = $namingService->getDownloadName($report->reportSchedule, $company, $report->customer, $report->generated_at) . '.pdf';
            } else {
                $filename = basename($report->file_path);
            }
            
            // Upload
            $result = $sharepoint->uploadFile(
                $content, 
                $filename, 
                $config['path'] ?? ''
            );

            return [
                'success' => true,
                'message' => 'Uploaded to SharePoint',
                'destination' => $config['path'] ?? '',
                'details' => [
                    'item_id' => $result['id'],
                    'web_url' => $result['web_url']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('SharePoint Delivery Failed', [
                'report' => $report->generated_report_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param Company $company
     * @return array{success: bool, message: string}
     */
    public function testConnection(array $config, Company $company): array
    {
        try {
            $integration = null;

            if (!empty($config['integration_id'])) {
                $integration = $company->integrations()->find($config['integration_id']);
            }

            if (!$integration) {
                $integration = $company->integrations()
                    ->where('integration_type', 'SharePoint')
                    ->where('status', 'active')
                    ->first();
            }

            if (!$integration instanceof \App\Models\Integration) {
                return [
                    'success' => false,
                    'message' => 'No active SharePoint integration found. Please configure one in Integrations settings.',
                ];
            }

            $sharepoint = new SharePointIntegration($integration);
            
            // Try to browse the path to verify it exists
            $sharepoint->browse($config['path'] ?? '');

            return [
                'success' => true,
                'message' => 'Connection successful. Folder exists.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
