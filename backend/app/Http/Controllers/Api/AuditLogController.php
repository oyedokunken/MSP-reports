<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs with filtering and pagination.
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $perPage = $request->input('per_page', 10);

        $query = AuditLog::where('company_id', $companyId)
            ->orderBy('created_at', 'desc');

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('actor_type', 'like', "%{$search}%")
                  ->orWhere('target_type', 'like', "%{$search}%")
                  ->orWhere('event_data', 'like', "%{$search}%");
            });
        }

        // Event Type Filter
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        // Actor Type Filter
        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->input('actor_type'));
        }

        // Target Type Filter
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        // Date Range Filter
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
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
    public function stats(Request $request)
    {
        $companyId = $request->user()->company_id;
        $today = Carbon::today();

        $totalLogs = AuditLog::where('company_id', $companyId)->count();
        $logsToday = AuditLog::where('company_id', $companyId)
            ->where('created_at', '>=', $today)
            ->count();
        
        $uniqueActors = AuditLog::where('company_id', $companyId)
            ->distinct('actor_id')
            ->count('actor_id');

        // Critical events - example heuristic
        $criticalEvents = AuditLog::where('company_id', $companyId)
            ->whereIn('event_type', [
                'user_deleted', 
                'api_key_created', 
                'failed_login_attempt',
                'security_settings_updated'
            ])
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_logs' => $totalLogs,
                'logs_today' => $logsToday,
                'unique_actors' => $uniqueActors,
                'critical_events' => $criticalEvents,
            ]
        ]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        $query = AuditLog::where('company_id', $companyId)
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        $logs = $query->get();

        $filename = "audit_logs_" . now()->format('Y-m-d_His') . ".csv";
        $handle = fopen('php://temp', 'w+');
        
        // CSV Header
        fputcsv($handle, ['ID', 'Event Type', 'Actor Type', 'Actor ID', 'Target Type', 'Target ID', 'Data', 'IP Address', 'Created At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->audit_log_id,
                $log->event_type,
                $log->actor_type,
                $log->actor_id,
                $log->target_type,
                $log->target_id,
                json_encode($log->event_data),
                $log->ip_address,
                $log->created_at->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
