<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Skip TimescaleDB-specific commands for SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Check if TimescaleDB extension is available
        $hasTimescale = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'timescaledb'");
        if (empty($hasTimescale)) {
            // TimescaleDB not installed, skip compression policies
            return;
        }

        // Enable compression on integration_data hypertable
        DB::statement("
            ALTER TABLE integration_data SET (
                timescaledb.compress,
                timescaledb.compress_segmentby = 'company_id,integration_id,data_type',
                timescaledb.compress_orderby = 'data_timestamp DESC'
            );
        ");

        // Add compression policy - compress chunks older than 7 days
        DB::statement("
            SELECT add_compression_policy('integration_data', 
                INTERVAL '7 days',
                if_not_exists => TRUE
            );
        ");

        // Add retention policy - drop chunks older than company's retention setting
        // Default: 1095 days (3 years) - can be adjusted per company
        DB::statement("
            SELECT add_retention_policy('integration_data', 
                INTERVAL '1095 days',
                if_not_exists => TRUE
            );
        ");
    }

    public function down(): void
    {
        // Skip TimescaleDB-specific commands for SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Remove policies
        DB::statement("SELECT remove_retention_policy('integration_data', if_exists => TRUE);");
        DB::statement("SELECT remove_compression_policy('integration_data', if_exists => TRUE);");
        
        // Disable compression
        DB::statement("ALTER TABLE integration_data SET (timescaledb.compress = false);");
    }
};
