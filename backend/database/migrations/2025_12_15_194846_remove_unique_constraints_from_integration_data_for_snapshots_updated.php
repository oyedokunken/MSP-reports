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
        Schema::table('integration_data_for_snapshots', function (Blueprint $table) {
            //
        });
        // Drop unique constraints that prevent time-series snapshots
        // This allows the same ticket/alert/device to appear multiple times
        // with different timestamps for accurate monthly reporting
        
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_unique_autotask_ticket');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_unique_alert_uid');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_unique_device_status');
        
        // Note: Reports use ->unique() in code to deduplicate
        // This gives us time-series snapshots while keeping report logic clean
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_data_for_snapshots', function (Blueprint $table) {
            //
        });
        // Recreate the unique constraints
        DB::statement("
            CREATE UNIQUE INDEX idx_integration_data_unique_autotask_ticket 
            ON integration_data (integration_id, data_type, data_timestamp, (data_payload->>'ticket_id'))
            WHERE data_type = 'ticket' AND (data_payload->>'ticket_id') IS NOT NULL
        ");
        
        DB::statement("
            CREATE UNIQUE INDEX idx_integration_data_unique_alert_uid 
            ON integration_data (integration_id, data_type, data_timestamp, (data_payload->>'alert_uid'))
            WHERE data_type = 'alert' AND (data_payload->>'alert_uid') IS NOT NULL
        ");
        
        DB::statement("
            CREATE UNIQUE INDEX idx_integration_data_unique_device_status 
            ON integration_data (integration_id, data_type, data_timestamp, (data_payload->>'device_uid'))
            WHERE data_type = 'device_status' AND (data_payload->>'device_uid') IS NOT NULL
        ");
    }
};