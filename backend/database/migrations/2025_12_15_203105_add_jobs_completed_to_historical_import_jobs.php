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
        Schema::table('historical_import_jobs', function (Blueprint $table) {
            $table->integer('jobs_completed')->default(0)->after('records_imported');
            $table->integer('total_jobs')->default(0)->after('jobs_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_import_jobs', function (Blueprint $table) {
            $table->dropColumn(['jobs_completed', 'total_jobs']);
        });
    }
};
