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
        Schema::table('report_templates', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('company_id')
                ->constrained('customers', 'customer_id')
                ->nullOnDelete();
            
            // Add index for customer lookups
            $table->index(['customer_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'active']);
            $table->dropColumn('customer_id');
        });
    }
};
