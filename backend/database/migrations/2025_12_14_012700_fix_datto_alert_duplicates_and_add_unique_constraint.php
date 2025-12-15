<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Delete duplicate Datto Alerts (keep most recent import)
        DB::statement("
            DELETE FROM integration_data 
            WHERE integration_data_id IN (
                SELECT integration_data_id FROM (
                    SELECT integration_data_id, 
                           ROW_NUMBER() OVER (
                               PARTITION BY integration_id, data_type, (data_payload->>'alert_uid')
                               ORDER BY imported_at DESC
                           ) as rn
                    FROM integration_data 
                    WHERE data_type = 'alert' 
                      AND integration_id IN (SELECT integration_id FROM integrations WHERE integration_type = 'Datto')
                      AND data_payload->>'alert_uid' IS NOT NULL
                ) t 
                WHERE rn > 1
            )
        ");

        // Step 2: Delete duplicate Datto Device Status records (keep most recent import)
        // Group by device_uid AND timestamp to allow history, but remove exact duplicates of the same snapshot
        DB::statement("
            DELETE FROM integration_data 
            WHERE integration_data_id IN (
                SELECT integration_data_id FROM (
                    SELECT integration_data_id, 
                           ROW_NUMBER() OVER (
                               PARTITION BY integration_id, data_type, data_timestamp, (data_payload->>'device_uid')
                               ORDER BY imported_at DESC
                           ) as rn
                    FROM integration_data 
                    WHERE data_type = 'device_status' 
                      AND integration_id IN (SELECT integration_id FROM integrations WHERE integration_type = 'Datto')
                      AND data_payload->>'device_uid' IS NOT NULL
                ) t 
                WHERE rn > 1
            )
        ");

        // Step 3: Delete duplicate Autotask Tickets (keep most recent import)
        DB::statement("
            DELETE FROM integration_data 
            WHERE integration_data_id IN (
                SELECT integration_data_id FROM (
                    SELECT integration_data_id, 
                           ROW_NUMBER() OVER (
                               PARTITION BY integration_id, data_type, data_timestamp, (data_payload->>'ticket_id')
                               ORDER BY imported_at DESC
                           ) as rn
                    FROM integration_data 
                    WHERE data_type = 'ticket' 
                      AND integration_id IN (SELECT integration_id FROM integrations WHERE integration_type = 'Autotask')
                      AND data_payload->>'ticket_id' IS NOT NULL
                ) t 
                WHERE rn > 1
            )
        ");

        // Step 4: Create unique index on Datto alert_uid
        // NOTE: TimescaleDB hypertables require data_timestamp in unique indexes
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_integration_data_unique_alert_uid 
            ON integration_data (integration_id, data_type, data_timestamp, ((data_payload->>'alert_uid')))
            WHERE data_type = 'alert' AND data_payload->>'alert_uid' IS NOT NULL
        ");

        // Step 5: Create unique index on Datto device_status
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_integration_data_unique_device_status
            ON integration_data (integration_id, data_type, data_timestamp, ((data_payload->>'device_uid')))
            WHERE data_type = 'device_status' AND data_payload->>'device_uid' IS NOT NULL
        ");

        // Step 6: Create unique index on Autotask ticket_id
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_integration_data_unique_autotask_ticket
            ON integration_data (integration_id, data_type, data_timestamp, ((data_payload->>'ticket_id')))
            WHERE data_type = 'ticket' AND data_payload->>'ticket_id' IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_integration_data_unique_alert_uid");
        DB::statement("DROP INDEX IF EXISTS idx_integration_data_unique_device_status");
        DB::statement("DROP INDEX IF EXISTS idx_integration_data_unique_autotask_ticket");
        // Note: We don't restore deleted duplicates in down()
    }
};
