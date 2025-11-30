<?php

namespace App\Console\Commands;

use App\Services\ReportRegistry;
use Illuminate\Console\Command;

class ListReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:list 
                            {--category= : Filter by category}
                            {--refresh : Refresh the registry cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available report types';

    /**
     * Execute the console command.
     */
    public function handle(ReportRegistry $registry): int
    {
        if ($this->option('refresh')) {
            $registry->clearCache();
            $registry->discover();
            $this->info('Registry cache refreshed');
            $this->newLine();
        }

        $reports = $registry->all();

        if (empty($reports)) {
            $this->warn('No reports found in registry');
            return Command::SUCCESS;
        }

        $category = $this->option('category');
        if ($category) {
            $reports = array_filter($reports, fn($r) => ($r['category'] ?? '') === $category);
            $this->info("Reports in category: {$category}");
        } else {
            $this->info("Available Reports ({$registry->count()}):");
        }

        $this->newLine();

        // Group by category
        $grouped = [];
        foreach ($reports as $id => $config) {
            $cat = $config['category'] ?? 'uncategorized';
            $grouped[$cat][] = array_merge(['id' => $id], $config);
        }

        foreach ($grouped as $cat => $catReports) {
            $this->line("<fg=cyan>Category: " . ucfirst($cat) . "</>");
            
            foreach ($catReports as $report) {
                $this->line("  <fg=green>{$report['id']}</>");
                $this->line("    Name: {$report['name']}");
                $this->line("    Description: {$report['description']}");
                $this->line("    Data Types: " . implode(', ', $report['data_types'] ?? []));
                $this->line("    Frequencies: " . implode(', ', $report['frequencies'] ?? []));
                $this->line("    Formats: " . implode(', ', $report['formats'] ?? []));
                $this->line("    Enabled: " . ($report['enabled'] ? 'Yes' : 'No'));
                $this->newLine();
            }
        }

        return Command::SUCCESS;
    }
}
