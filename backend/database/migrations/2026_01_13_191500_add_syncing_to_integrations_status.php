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
        // Only run constraint changes on PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop the existing check constraint
            DB::statement("ALTER TABLE integrations DROP CONSTRAINT IF EXISTS integrations_last_sync_status_check");

            // Add the new check constraint with 'syncing' included
            DB::statement("ALTER TABLE integrations ADD CONSTRAINT integrations_last_sync_status_check CHECK (last_sync_status IN ('success', 'failed', 'partial', 'syncing'))");
        }
        // SQLite doesn't support ALTER TABLE for constraints, but also doesn't enforce them strictly
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Revert to original allowed values
            DB::statement("ALTER TABLE integrations DROP CONSTRAINT IF EXISTS integrations_last_sync_status_check");
            DB::statement("ALTER TABLE integrations ADD CONSTRAINT integrations_last_sync_status_check CHECK (last_sync_status IN ('success', 'failed', 'partial'))");
        }
    }
};
