<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IntegrationPreviewController extends Controller
{
    /**
     * Get the display template for an integration
     */
    public function getTemplate(Integration $integration): JsonResponse
    {
        try {
            // Ensure user has access to this integration
            if ($integration->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                ], 403);
            }

            // Get integration instance
            $integrationClass = $this->getIntegrationClass($integration);
            $integrationInstance = new $integrationClass($integration);

            // Get display template
            $template = $integrationInstance->getDisplayTemplate();

            return response()->json([
                'success' => true,
                'template' => $template,
                'integration' => [
                    'id' => $integration->integration_id,
                    'name' => $integration->display_name,
                    'type' => $integration->integration_type,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get integration template', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get preview data for an integration
     */
    public function getPreviewData(Integration $integration, Request $request): JsonResponse
    {
        try {
            // Ensure user has access to this integration
            if ($integration->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                ], 403);
            }

            // Get integration instance
            $integrationClass = $this->getIntegrationClass($integration);
            $integrationInstance = new $integrationClass($integration);

            // Get optional customer filter
            $integrationCustomerId = $request->query('customer_id');

            // Get preview data
            $previewData = $integrationInstance->getPreviewData($integrationCustomerId);

            return response()->json($previewData);

        } catch (\Exception $e) {
            Log::error('Failed to get integration preview data', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load preview data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get both template and data in a single request
     */
    public function getPreview(Integration $integration, Request $request): JsonResponse
    {
        try {
            // Ensure user has access to this integration
            if ($integration->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                ], 403);
            }

            // Get integration instance
            $integrationClass = $this->getIntegrationClass($integration);
            
            Log::info('Creating integration instance', [
                'class' => $integrationClass,
                'integration_id' => $integration->integration_id,
            ]);
            
            $integrationInstance = new $integrationClass($integration);

            // Get optional customer filter
            $integrationCustomerId = $request->query('customer_id');

            // Get both template and data
            Log::info('Getting template');
            $template = $integrationInstance->getDisplayTemplate();
            
            Log::info('Getting preview data', ['customer_id' => $integrationCustomerId]);
            $previewData = $integrationInstance->getPreviewData($integrationCustomerId);
            
            Log::info('Preview data retrieved', [
                'has_data' => isset($previewData['data']),
                'success' => $previewData['success'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'integration' => [
                    'id' => $integration->integration_id,
                    'name' => $integration->display_name,
                    'type' => $integration->integration_type,
                ],
                'template' => $template,
                'preview' => $previewData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get integration preview', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load preview: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get licenses for a specific customer (ITGlue only)
     */
    public function getCustomerLicenses(Integration $integration, Request $request): JsonResponse
    {
        try {
            // Ensure user has access to this integration
            if ($integration->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                ], 403);
            }

            $integrationCustomerId = $request->query('customer_id');
            if (!$integrationCustomerId) {
                return response()->json([
                    'success' => false,
                    'error' => 'customer_id is required',
                ], 400);
            }

            // Get integration instance
            $integrationClass = $this->getIntegrationClass($integration);
            $integrationInstance = new $integrationClass($integration);

            // Check if integration has getCustomerLicenses method
            if (!method_exists($integrationInstance, 'getCustomerLicenses')) {
                return response()->json([
                    'success' => false,
                    'error' => 'This integration does not support license fetching',
                ], 400);
            }

            $licenses = $integrationInstance->getCustomerLicenses($integrationCustomerId);

            return response()->json([
                'success' => true,
                'licenses' => $licenses,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customer licenses', [
                'integration_id' => $integration->integration_id,
                'customer_id' => $request->query('customer_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load licenses: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the integration class from the integration type
     */
    private function getIntegrationClass(Integration $integration): string
    {
        // Get the integration type (e.g., "Datto", "M365", "ActiveDirectory")
        $type = $integration->integration_type;
        
        // Build the fully qualified class name
        $fullClassName = "App\\Integrations\\{$type}\\{$type}Integration";

        if (!class_exists($fullClassName)) {
            throw new \Exception("Integration class not found: {$fullClassName}");
        }

        return $fullClassName;
    }
}
