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
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('report_schedule_id');
            
            // Add foreign key constraint with cascade delete
            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('report_templates')
                  ->onDelete('cascade');
                  
            // Add index for performance
            $table->index('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
