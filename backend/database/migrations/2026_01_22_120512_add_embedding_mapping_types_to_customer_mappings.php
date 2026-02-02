<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add new mapping types for embedding and agent-based matching.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop the old CHECK constraint
        DB::statement("ALTER TABLE customer_mappings DROP CONSTRAINT IF EXISTS customer_mappings_mapping_type_check");
        
        // Add new constraint with expanded values
        DB::statement("ALTER TABLE customer_mappings ADD CONSTRAINT customer_mappings_mapping_type_check CHECK (mapping_type IN ('auto', 'manual', 'auto_pending', 'auto_embedding', 'auto_agent'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any new types back to 'auto' before removing the constraint
        DB::statement("UPDATE customer_mappings SET mapping_type = 'auto' WHERE mapping_type IN ('auto_embedding', 'auto_agent', 'auto_pending')");
        
        // Drop the new constraint
        DB::statement("ALTER TABLE customer_mappings DROP CONSTRAINT IF EXISTS customer_mappings_mapping_type_check");
        
        // Add back the original constraint
        DB::statement("ALTER TABLE customer_mappings ADD CONSTRAINT customer_mappings_mapping_type_check CHECK (mapping_type IN ('auto', 'manual'))");
    }
};
