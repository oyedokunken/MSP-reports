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
            $table->timestamp('import_start_date')->change();
            $table->timestamp('import_end_date')->nullable()->change();
            $table->timestamp('current_position')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_import_jobs', function (Blueprint $table) {
            $table->date('import_start_date')->change();
            $table->date('import_end_date')->nullable()->change();
            $table->date('current_position')->nullable()->change();
        });
    }
};
