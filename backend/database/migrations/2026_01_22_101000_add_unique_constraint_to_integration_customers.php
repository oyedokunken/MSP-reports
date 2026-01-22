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
        Schema::table('integration_customers', function (Blueprint $table) {
            // Drop existing non-unique index if it exists
            // The name was likely 'idx_integration_customers_external' from previous optimization migration
            // or Laravel's default 'integration_customers_integration_id_external_id_index'
            
            $indexName = 'idx_integration_customers_external';
            
            // Check if it exists before dropping
            $exists = DB::selectOne(
                "SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = ?) as exists",
                [$indexName]
            )->exists;

            if ($exists) {
                $table->dropIndex($indexName);
            }

            // Add unique index required for upsert
            $table->unique(['integration_id', 'external_id'], 'idx_integration_customers_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_customers', function (Blueprint $table) {
            $table->dropUnique('idx_integration_customers_external_unique');
            
            // Re-add as non-unique index
            $table->index(['integration_id', 'external_id'], 'idx_integration_customers_external');
        });
    }
};
