<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\GeneratedReport;
use App\Models\ReportShareToken;
use App\Services\ReportNamingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadReportAction
{
    public function __construct(
        private ReportNamingService $namingService
    ) {}

    public function execute(int $companyId, int $reportId): StreamedResponse
    {
        $report = GeneratedReport::withTrashed()
            ->where('generated_report_id', $reportId)
            ->where('company_id', $companyId)
            ->with(['customer:customer_id,canonical_name', 'reportSchedule:report_schedule_id,report_type,frequency', 'company'])
            ->first();

        if (!$report) {
            throw ValidationException::withMessages([
                'report' => ['Report not found or access denied.'],
            ]);
        }

        return $this->download($report);
    }

    public function executeShared(string $token): StreamedResponse
    {
        $shareToken = ReportShareToken::where('token', $token)
            ->with(['generatedReport.customer', 'generatedReport.reportSchedule', 'generatedReport.company'])
            ->first();

        if (!$shareToken) {
            throw ValidationException::withMessages([
                'token' => ['Invalid share link.'],
            ]);
        }

        if ($shareToken->isExpired()) {
            throw ValidationException::withMessages([
                'token' => ['This share link has expired.'],
            ]);
        }

        // Verify HMAC signature to detect tampering
        if ($shareToken->signature && !$shareToken->verifySignature()) {
            Log::warning('Share link signature verification failed', [
                'token' => $token,
                'report_id' => $shareToken->generated_report_id,
            ]);
            throw ValidationException::withMessages([
                'token' => ['Invalid share link signature.'],
            ]);
        }

        $report = $shareToken->generatedReport;

        Log::info('Shared report downloaded', [
            'report_id' => $report->generated_report_id,
            'token' => $token,
        ]);

        return $this->download($report);
    }

    private function download(GeneratedReport $report): StreamedResponse
    {
        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            throw ValidationException::withMessages([
                'file' => ['Report file not found.'],
            ]);
        }

        // Use ReportNamingService for consistent filename generation
        $downloadFilename = 'Report.pdf';
        
        if ($report->reportSchedule) {
            $downloadFilename = $this->namingService->getDownloadName($report->reportSchedule, $report->company, $report->customer, $report->generated_at) . '.pdf';
        } else {
            // Fallback for reports without schedules
            $downloadFilename = basename($report->file_path);
        }

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
            'X-Report-Name' => $downloadFilename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at->toDateTimeString(),
        ]);
    }
}
