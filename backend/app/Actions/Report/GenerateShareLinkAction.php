<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Concerns\LogsAudit;
use App\Models\GeneratedReport;
use App\Models\ReportShareToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class GenerateShareLinkAction
{
    use LogsAudit;

    /**
     * @return array<string, mixed>
     */
    public function execute(GeneratedReport $report, User $user, string $baseUrl): array
    {
        if ($report->status !== 'completed') {
            throw ValidationException::withMessages([
                'report_id' => 'Report is not ready for sharing.'
            ]);
        }

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            throw ValidationException::withMessages([
                'report_id' => 'Report file not found.'
            ]);
        }

        // Generate a secure token with HMAC signature
        $secureToken = ReportShareToken::generateSecureToken(
            $report->generated_report_id,
            $user->company_id
        );
        
        // Create share token
        $shareToken = ReportShareToken::create([
            'generated_report_id' => $report->generated_report_id,
            'company_id' => $user->company_id,
            'token' => $secureToken['token'],
            'signature' => $secureToken['signature'],
            'expires_at' => $secureToken['expires_at'],
        ]);

        $shareUrl = "{$baseUrl}/api/reports/shared/{$secureToken['token']}";

        Log::info('Share link generated', [
            'report_id' => $report->generated_report_id,
            'expires_at' => $shareToken->expires_at,
            'user' => $user->email,
        ]);

        $this->audit($user->company_id, 'report_shared', $report, [
            'report_type' => $report->reportSchedule?->report_type ?? 'Unknown',
            'customer_name' => $report->customer?->canonical_name ?? 'Unknown',
            'expires_at' => $shareToken->expires_at->toIso8601String(),
        ]);

        return [
            'share_url' => $shareUrl,
            'expires_at' => $shareToken->expires_at->toIso8601String(),
            'expires_in_minutes' => 60,
        ];
    }
}
