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
            $table->string('external_id')->nullable()->after('integration_customer_id');
            $table->index(['integration_id', 'data_type', 'external_id'], 'idx_integration_data_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_data', function (Blueprint $table) {
            $table->dropIndex('idx_integration_data_lookup');
            $table->dropColumn('external_id');
        });
    }
};
