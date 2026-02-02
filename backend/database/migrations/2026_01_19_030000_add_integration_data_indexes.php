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
        if (DB::connection()->getDriverName() !== 'pgsql') {
            // Basic index for non-postgres drivers (testing)
            Schema::table('integration_data', function (Blueprint $table) {
                $table->index(['integration_id', 'data_type', 'integration_customer_id'], 'idx_integration_data_type_customer');
            });
            return;
        }

        // Add GIN index for general JSONB queries
        DB::statement('CREATE INDEX IF NOT EXISTS idx_integration_data_payload_gin ON integration_data USING GIN (data_payload)');
        
        // Add expression indexes for commonly queried fields
        DB::statement("CREATE INDEX IF NOT EXISTS idx_integration_data_device_uid ON integration_data ((data_payload->>'device_uid')) WHERE data_type = 'device_status'");
        
        DB::statement("CREATE INDEX IF NOT EXISTS idx_integration_data_alert_uid ON integration_data ((data_payload->>'alert_uid')) WHERE data_type = 'alert'");
        
        DB::statement("CREATE INDEX IF NOT EXISTS idx_integration_data_ticket_id ON integration_data ((data_payload->>'ticket_id')) WHERE data_type = 'ticket'");
        
        DB::statement("CREATE INDEX IF NOT EXISTS idx_integration_data_time_entry_id ON integration_data ((data_payload->>'time_entry_id')) WHERE data_type = 'time_entry'");
        
        DB::statement("CREATE INDEX IF NOT EXISTS idx_integration_data_asset_id ON integration_data ((data_payload->>'id')) WHERE data_type IN ('ssl_certificate', 'domain', 'flexible_asset')");
        
        // Composite index for common query patterns
        DB::statement('CREATE INDEX IF NOT EXISTS idx_integration_data_type_customer ON integration_data (integration_id, data_type, integration_customer_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('integration_data', function (Blueprint $table) {
                $table->dropIndex('idx_integration_data_type_customer');
            });
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_integration_data_payload_gin');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_device_uid');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_alert_uid');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_ticket_id');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_time_entry_id');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_asset_id');
        DB::statement('DROP INDEX IF EXISTS idx_integration_data_type_customer');
    }
};
