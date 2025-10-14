<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id('integration_id');

            // Foreign key to companies
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();

            // Core attributes
            $table->string('integration_type'); // e.g., Datto, Autotask
            $table->string('display_name');
            $table->enum('status', ['active', 'disconnected', 'error', 'importing_historical'])->default('disconnected');

            $table->timestamp('last_sync_at')->nullable();
            $table->enum('last_sync_status', ['success', 'failed', 'partial'])->nullable();
            $table->text('error_message')->nullable();

            $table->jsonb('credentials')->nullable(); // encrypted JSON
            $table->jsonb('configuration')->nullable(); // integration-specific settings

            $table->boolean('is_source_of_truth')->default(false);

            $table->enum('historical_import_status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedTinyInteger('historical_import_progress')->default(0); // 0-100%
            $table->timestamp('last_historical_sync_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
