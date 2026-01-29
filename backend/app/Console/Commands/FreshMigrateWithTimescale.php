<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FreshMigrateWithTimescale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:fresh-timescale {--seed : Seed the database after migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables including TimescaleDB hypertables and re-run all migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->info('Dropping all tables including TimescaleDB hypertables...');

        // Step 1: Get all hypertables
        $hypertables = DB::select("
            SELECT hypertable_name 
            FROM timescaledb_information.hypertables 
            WHERE hypertable_schema = 'public'
        ");

        // Step 2: Drop each hypertable individually
        foreach ($hypertables as $hypertable) {
            $tableName = $hypertable->hypertable_name;
            $this->info("Dropping hypertable: {$tableName}");
            DB::statement("DROP TABLE IF EXISTS {$tableName} CASCADE");
        }

        // Step 3: Get all continuous aggregates
        $aggregates = DB::select("
            SELECT view_name 
            FROM timescaledb_information.continuous_aggregates 
            WHERE view_schema = 'public'
        ");

        // Step 4: Drop each continuous aggregate
        foreach ($aggregates as $aggregate) {
            $viewName = $aggregate->view_name;
            $this->info("Dropping continuous aggregate: {$viewName}");
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$viewName} CASCADE");
        }

        // Step 5: Get all remaining tables
        $tables = DB::select("
            SELECT tablename 
            FROM pg_tables 
            WHERE schemaname = 'public'
        ");

        // Step 6: Drop all remaining tables
        if (count($tables) > 0) {
            $tableNames = array_map(fn($t) => '"' . $t->tablename . '"', $tables);
            $tableList = implode(', ', $tableNames);
            $this->info("Dropping remaining tables...");
            DB::statement("DROP TABLE IF EXISTS {$tableList} CASCADE");
        }

        // Step 7: Run migrations
        $this->info('Running migrations...');
        $this->call('migrate');

        // Step 8: Seed if requested
        if ($this->option('seed')) {
            $this->info('Seeding database...');
            $this->call('db:seed');
        }

        $this->info('Database reset complete!');

        return 0;
    }

    /**
     * Confirm before proceeding with the command.
     */
    protected function confirmToProceed(): bool
    {
        if ($this->getLaravel()->environment() === 'production') {
            $this->error('This command cannot be run in production!');
            return false;
        }

        return true;
    }
}
