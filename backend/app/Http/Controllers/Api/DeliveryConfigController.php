<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Http\Requests\Delivery\UpdateDeliveryConfigRequest;
use App\Http\Requests\Delivery\TestDeliveryConnectionRequest;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Support\Facades\Log;

final class DeliveryConfigController extends Controller
{
    protected DeliveryManager $deliveryManager;

    public function __construct(DeliveryManager $deliveryManager)
    {
        $this->deliveryManager = $deliveryManager;
    }

    /**
     * Get available delivery methods and their configuration schemas
     */
    public function getAvailableMethods(Request $request): \Illuminate\Http\JsonResponse
    {
        $methods = $this->deliveryManager->getAvailableMethods();

        return response()->json([
            'status' => 'success',
            'data' => $methods,
        ]);
    }

    /**
     * Get delivery configuration for a customer
     */
    public function getCustomerDeliveryConfig(Request $request, int $customerId): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customerId)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.',
            ], 404);
        }

        $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
        $availableMethods = $this->deliveryManager->getAvailableMethods();

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->canonical_name,
                'delivery_config' => $deliveryConfig,
                'available_methods' => $availableMethods,
            ],
        ]);
    }

    /**
     * Update delivery configuration for a customer
     */
    public function updateCustomerDeliveryConfig(UpdateDeliveryConfigRequest $request, int $customerId): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customerId)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.',
            ], 404);
        }

        $validated = $request->validated();

        // Validate each enabled method has proper config
        $validation = $this->deliveryManager->validateConfig($validated);
        if (!$validation['valid']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid delivery configuration.',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Update customer's report_configuration
        $reportConfig = $customer->report_configuration ?? [];
        $reportConfig['delivery'] = $validated;
        $customer->report_configuration = $reportConfig;
        $customer->save();

        Log::info('Customer delivery configuration updated', [
            'customer_id' => $customer->customer_id,
            'enabled_methods' => $validated['enabled_methods'],
            'updated_by' => $authUser->user_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery configuration updated successfully.',
            'data' => $reportConfig['delivery'],
        ]);
    }

    /**
     * Test a delivery method connection
     */
    public function testConnection(TestDeliveryConnectionRequest $request, int $customerId): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customerId)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.',
            ], 404);
        }

        $validated = $request->validated();

        $result = $this->deliveryManager->testConnection(
            $validated['method'],
            $validated['config'],
            $company
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result,
        ], $result['success'] ? 200 : 400);
    }
}
