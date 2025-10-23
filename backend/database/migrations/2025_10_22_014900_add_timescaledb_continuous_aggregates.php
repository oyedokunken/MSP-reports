<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Create daily continuous aggregate for integration_data
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS integration_data_daily
            WITH (timescaledb.continuous) AS
            SELECT 
                time_bucket('1 day', data_timestamp) AS bucket,
                company_id,
                integration_id,
                integration_customer_id,
                data_type,
                COUNT(*) as record_count,
                MIN(data_timestamp) as first_timestamp,
                MAX(data_timestamp) as last_timestamp
            FROM integration_data
            GROUP BY bucket, company_id, integration_id, integration_customer_id, data_type
            WITH NO DATA;
        ");

        // Add refresh policy - refresh every hour for last 7 days
        DB::statement("
            SELECT add_continuous_aggregate_policy('integration_data_daily',
                start_offset => INTERVAL '7 days',
                end_offset => INTERVAL '1 hour',
                schedule_interval => INTERVAL '1 hour',
                if_not_exists => TRUE
            );
        ");

        // Create monthly continuous aggregate
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS integration_data_monthly
            WITH (timescaledb.continuous) AS
            SELECT 
                time_bucket('1 month', data_timestamp) AS bucket,
                company_id,
                integration_id,
                integration_customer_id,
                data_type,
                COUNT(*) as record_count,
                MIN(data_timestamp) as first_timestamp,
                MAX(data_timestamp) as last_timestamp
            FROM integration_data
            GROUP BY bucket, company_id, integration_id, integration_customer_id, data_type
            WITH NO DATA;
        ");

        // Add refresh policy - refresh daily for last 3 months
        DB::statement("
            SELECT add_continuous_aggregate_policy('integration_data_monthly',
                start_offset => INTERVAL '3 months',
                end_offset => INTERVAL '1 day',
                schedule_interval => INTERVAL '1 day',
                if_not_exists => TRUE
            );
        ");

        // Note: Initial refresh will happen automatically via the policies
        // Manual refresh can be done with: CALL refresh_continuous_aggregate('integration_data_daily', NULL, NULL);
    }

    public function down(): void
    {
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS integration_data_monthly CASCADE;");
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS integration_data_daily CASCADE;");
    }
};
