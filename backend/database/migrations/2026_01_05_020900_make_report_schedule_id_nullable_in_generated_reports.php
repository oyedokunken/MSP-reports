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
        Schema::table('generated_reports', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['report_schedule_id']);
            
            // Make the column nullable
            $table->foreignId('report_schedule_id')
                ->nullable()
                ->change();
            
            // Re-add the foreign key constraint with nullable
            $table->foreign('report_schedule_id')
                ->references('report_schedule_id')
                ->on('report_schedules')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            // Drop the nullable foreign key
            $table->dropForeign(['report_schedule_id']);
            
            // Make it NOT NULL again
            $table->foreignId('report_schedule_id')
                ->nullable(false)
                ->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('report_schedule_id')
                ->references('report_schedule_id')
                ->on('report_schedules')
                ->cascadeOnDelete();
        });
    }
};
