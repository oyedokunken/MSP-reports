<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('sync_schedule')->nullable()->after('last_sync_status');
        });
        
        // Set integration-type-specific defaults
        DB::table('integrations')
            ->where('integration_type', 'Autotask')
            ->whereNull('sync_schedule')
            ->update(['sync_schedule' => '0 */2 * * *']);
            
        DB::table('integrations')
            ->where('integration_type', 'Datto')
            ->whereNull('sync_schedule')
            ->update(['sync_schedule' => '*/5 * * * *']);
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('sync_schedule');
        });
    }
};
