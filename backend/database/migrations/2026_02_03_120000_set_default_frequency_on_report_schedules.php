<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure frequency column has a default value and is not null.
     * This prevents runtime errors when frequency is accessed as an enum.
     */
    public function up(): void
    {
        // First, update any existing null values to 'monthly'
        DB::table('report_schedules')
            ->whereNull('frequency')
            ->update(['frequency' => 'monthly']);

        // Set default value for the column
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->string('frequency')->default('monthly')->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->string('frequency')->default(null)->change();
        });
    }
};
