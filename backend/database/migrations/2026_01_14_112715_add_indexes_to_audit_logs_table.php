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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['actor_type', 'actor_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['actor_type', 'actor_id']);
            $table->dropIndex(['target_type', 'target_id']);
        });
    }
};
