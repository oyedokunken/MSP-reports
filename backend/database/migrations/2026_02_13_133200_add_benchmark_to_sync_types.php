<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'benchmark' to the allowed check constraint values
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_sync_type_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_sync_type_check CHECK (sync_type IN ('scheduled', 'manual', 'webhook', 'initial', 'retry', 'current_state', 'benchmark'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE sync_logs DROP CONSTRAINT IF EXISTS sync_logs_sync_type_check");
        DB::statement("ALTER TABLE sync_logs ADD CONSTRAINT sync_logs_sync_type_check CHECK (sync_type IN ('scheduled', 'manual', 'webhook', 'initial', 'retry', 'current_state'))");
    }
};
