<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'queued' to the status enum
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_status_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_status_check CHECK (status IN ('queued', 'running', 'success', 'failed', 'partial'))");
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_status_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_status_check CHECK (status IN ('running', 'success', 'failed', 'partial'))");
    }
};
