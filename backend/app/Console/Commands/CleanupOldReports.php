<?php

namespace App\Console\Commands;

use App\Models\GeneratedReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days= : Override retention period (days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired generated reports based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info($isDryRun ? 'Running in DRY RUN mode...' : 'Cleaning up old reports...');

        try {
            // Find expired reports
            $expiredReports = GeneratedReport::where('retention_until', '<', now())
                ->where('status', 'completed')
                ->get();

            if ($expiredReports->isEmpty()) {
                $this->info('No expired reports found.');
                return Command::SUCCESS;
            }

            $this->info("Found {$expiredReports->count()} expired reports");

            $deleted = 0;
            $failed = 0;
            $totalSize = 0;

            foreach ($expiredReports as $report) {
                $size = $report->file_size ?? 0;
                $totalSize += $size;

                if ($isDryRun) {
                    $this->line("  Would delete: {$report->file_path} ({$this->formatBytes($size)})");
                    $deleted++;
                } else {
                    try {
                        // Delete file if exists
                        if ($report->file_path && Storage::disk('reports')->exists($report->file_path)) {
                            Storage::disk('reports')->delete($report->file_path);
                        }

                        // Delete database record
                        $report->delete();
                        
                        $deleted++;
                        $this->line("  ✓ Deleted: {$report->file_path}");
                    } catch (\Exception $e) {
                        $failed++;
                        $this->error("  ✗ Failed to delete {$report->file_path}: {$e->getMessage()}");
                    }
                }
            }

            $this->newLine();
            $this->info("Cleanup completed:");
            $this->line("  " . ($isDryRun ? 'Would delete' : 'Deleted') . ": {$deleted} reports");
            $this->line("  Space " . ($isDryRun ? 'would be' : '') . " freed: {$this->formatBytes($totalSize)}");
            
            if ($failed > 0) {
                $this->error("  Failed: {$failed} reports");
            }

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? (int)floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
