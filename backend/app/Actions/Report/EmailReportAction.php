<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Jobs\DeliverReportJob;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class EmailReportAction
{
    /**
     * @param array<int, string>|string $emails
     * @return array<string, mixed>
     */
    public function execute(GeneratedReport $report, $emails, User $user): array
    {
        if ($report->status !== 'completed') {
            throw ValidationException::withMessages([
                'report_id' => 'Report is not ready for delivery.'
            ]);
        }

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            throw ValidationException::withMessages([
                'report_id' => 'Report file not found.'
            ]);
        }

        // Parse and validate email addresses
        $emailAddresses = is_string($emails) ? array_map('trim', explode(',', $emails)) : $emails;
        $emailAddresses = array_filter($emailAddresses);

        $invalidEmails = [];
        foreach ($emailAddresses as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $email;
            }
        }

        if (!empty($invalidEmails)) {
            throw ValidationException::withMessages([
                'emails' => 'Invalid email address(es): ' . implode(', ', $invalidEmails)
            ]);
        }

        if (empty($emailAddresses)) {
            throw ValidationException::withMessages([
                'emails' => 'Please provide at least one valid email address.'
            ]);
        }

        // Dispatch the email job
        DeliverReportJob::dispatch($report, $emailAddresses);

        Log::info('Report email queued', [
            'report_id' => $report->generated_report_id,
            'customer' => $report->customer->canonical_name ?? 'Unknown',
            'recipients' => $emailAddresses,
            'user' => $user->email,
        ]);

        return [
            'report_id' => $report->generated_report_id,
            'customer_name' => $report->customer->canonical_name ?? 'Unknown',
            'email_recipients' => $emailAddresses,
            'report_type' => $report->reportSchedule->report_type ?? 'Unknown',
        ];
    }
}
