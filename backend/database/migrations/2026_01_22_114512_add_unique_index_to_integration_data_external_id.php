<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integration_data', function (Blueprint $table) {
            // Drop existing non-unique index if it exists
            $indexes = Schema::getIndexes('integration_data');
            $hasIndex = collect($indexes)->contains(fn($index) => $index['name'] === 'idx_integration_data_lookup');
            
            if ($hasIndex) {
                 $table->dropIndex('idx_integration_data_lookup');
            }
            
            // For TimescaleDB Hypertables, unique indexes MUST include the partitioning column (data_timestamp)
            $table->unique(['integration_id', 'data_type', 'external_id', 'data_timestamp'], 'idx_integration_data_unique_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_data', function (Blueprint $table) {
            $table->dropUnique('idx_integration_data_unique_external');
            $table->index(['integration_id', 'data_type', 'external_id'], 'idx_integration_data_lookup');
        });
    }
};
