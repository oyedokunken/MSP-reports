<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\GetAuditLogsRequest;
use App\Http\Requests\AuditLog\GetAuditLogStatsRequest;
use App\Http\Resources\AuditLogResource;
use App\Actions\AuditLog\GetAuditLogsAction;
use App\Actions\AuditLog\GetAuditLogStatsAction;
use App\Actions\AuditLog\ExportAuditLogsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs with filtering and pagination.
     */
    public function index(GetAuditLogsRequest $request, GetAuditLogsAction $action): JsonResponse
    {
        $user = $request->user();
        /** @var array{per_page?: int, search?: string, event_type?: string, actor_type?: string, target_type?: string, date_from?: string, date_to?: string} $validated */
        $validated = $request->validated();
        
        $perPage = $validated['per_page'] ?? $request->input('per_page', 10);

        $query = $action->execute($user->company_id, $validated);
        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => AuditLogResource::collection($logs->items()),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }

    /**
     * Get aggregate statistics for audit logs.
     */
    public function stats(GetAuditLogStatsRequest $request, GetAuditLogStatsAction $action): JsonResponse
    {
        $stats = $action->execute($request->user()->company_id);

        return $this->success($stats);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(GetAuditLogsRequest $request, GetAuditLogsAction $getLogsAction, ExportAuditLogsAction $exportAction): Response
    {
        $user = $request->user();
        /** @var array{search?: string, event_type?: string, actor_type?: string, target_type?: string, date_from?: string, date_to?: string} $validated */
        $validated = $request->validated();
        
        $logs = $getLogsAction->execute($user->company_id, $validated)->get();
        $csvContent = $exportAction->execute($logs);

        $filename = "audit_logs_" . now()->format('Y-m-d_His') . ".csv";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
