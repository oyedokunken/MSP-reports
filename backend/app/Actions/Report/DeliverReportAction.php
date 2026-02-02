<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Jobs\DeliverReportJob;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DeliverReportAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(GeneratedReport $report, User $user): array
    {
        if ($report->status !== 'completed') {
            throw ValidationException::withMessages([
                'report_id' => 'Report is not ready for delivery.'
            ]);
        }

        // Dispatch the report delivery job
        DeliverReportJob::dispatch($report);

        Log::info('Report delivery initiated', [
            'report_id' => $report->generated_report_id,
            'customer' => $report->customer->canonical_name ?? 'Unknown',
            'user' => $user->email,
        ]);

        return [
            'report_id' => $report->generated_report_id,
            'customer_name' => $report->customer->canonical_name ?? 'Unknown',
            'status' => 'delivery_queued',
        ];
    }
}
