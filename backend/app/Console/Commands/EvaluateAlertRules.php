<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AlertEngine;
use Illuminate\Support\Facades\Log;

class EvaluateAlertRules extends Command
{
    protected $signature = 'alerts:evaluate {--dry-run : Show what would be triggered without actually triggering}';
    protected $description = 'Evaluate all active alert rules and trigger notifications';

    public function handle(AlertEngine $alertEngine): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No alerts will be triggered');
        }

        $this->info('Evaluating alert rules...');
        $this->newLine();

        try {
            if ($dryRun) {
                $this->showDryRun();
            } else {
                $results = $alertEngine->evaluateAllRules();

                $this->info("Alert Evaluation Complete:");
                $this->line("  - Rules Evaluated: {$results['evaluated']}");
                $this->line("  - Alerts Triggered: {$results['triggered']}");
                $this->line("  - Skipped (Cooldown): {$results['skipped_cooldown']}");
                
                if ($results['errors'] > 0) {
                    $this->error("  - Errors: {$results['errors']}");
                }

                Log::info('Alert evaluation completed', $results);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Alert evaluation failed: ' . $e->getMessage());
            Log::error('Alert evaluation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    protected function showDryRun(): void
    {
        $rules = \App\Models\AlertRule::where('is_active', true)->get();

        $this->info("Active Alert Rules: {$rules->count()}");
        $this->newLine();

        foreach ($rules as $rule) {
            $this->line("Rule: {$rule->name}");
            $this->line("  Severity: {$rule->severity}");
            $this->line("  Metric: {$rule->metric_type}");
            $this->line("  Condition: {$rule->condition['operator']} {$rule->condition['threshold']}");
            $this->line("  Cooldown: {$rule->cooldown_minutes} minutes");
            
            if ($rule->isInCooldown()) {
                $remaining = $rule->cooldownRemainingMinutes();
                $this->warn("  Status: IN COOLDOWN ({$remaining} minutes remaining)");
            } else {
                $this->info("  Status: ACTIVE");
            }
            
            $this->newLine();
        }
    }
}
