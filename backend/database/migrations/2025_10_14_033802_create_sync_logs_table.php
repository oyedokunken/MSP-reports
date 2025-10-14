<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id('sync_id');

            $table->foreignId('integration_id')
                ->constrained('integrations', 'integration_id')
                ->cascadeOnDelete();

            $table->enum('sync_type', ['current_state', 'historical', 'backfill'])->default('current_state');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('error_messages')->nullable();
            $table->integer('rate_limit_hits')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
