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
        // For TimescaleDB hypertables, we need to use raw SQL
        // Drop the foreign key constraint first
        DB::statement('ALTER TABLE integration_data DROP CONSTRAINT IF EXISTS integration_data_integration_customer_id_foreign');
        
        // Make the column nullable using raw SQL
        DB::statement('ALTER TABLE integration_data ALTER COLUMN integration_customer_id DROP NOT NULL');
        
        // Re-add the foreign key constraint with nullable support
        DB::statement('
            ALTER TABLE integration_data 
            ADD CONSTRAINT integration_data_integration_customer_id_foreign 
            FOREIGN KEY (integration_customer_id) 
            REFERENCES integration_customers(integration_customer_id) 
            ON DELETE SET NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key
        DB::statement('ALTER TABLE integration_data DROP CONSTRAINT IF EXISTS integration_data_integration_customer_id_foreign');
        
        // Make the column NOT nullable again
        DB::statement('ALTER TABLE integration_data ALTER COLUMN integration_customer_id SET NOT NULL');
        
        // Re-add the original foreign key constraint
        DB::statement('
            ALTER TABLE integration_data 
            ADD CONSTRAINT integration_data_integration_customer_id_foreign 
            FOREIGN KEY (integration_customer_id) 
            REFERENCES integration_customers(integration_customer_id) 
            ON DELETE CASCADE
        ');
    }
};
