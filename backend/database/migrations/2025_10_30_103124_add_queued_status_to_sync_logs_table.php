<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Skip constraint modifications for SQLite (SQLite doesn't support ALTER TABLE DROP CONSTRAINT)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Add 'queued' to the status enum
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_status_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_status_check CHECK (status IN ('queued', 'running', 'success', 'failed', 'partial'))");
    }

    public function down(): void
    {
        // Skip constraint modifications for SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Revert to original enum values
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_status_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_status_check CHECK (status IN ('running', 'success', 'failed', 'partial'))");
    }
};
