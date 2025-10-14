<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Regular table first
        Schema::create('integration_data', function (Blueprint $table) {
            // Keep a standard primary key
            $table->bigIncrements('integration_data_id');

            // Foreign keys
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
            $table->foreignId('integration_id')
                ->constrained('integrations', 'integration_id')
                ->cascadeOnDelete();
            $table->foreignId('integration_customer_id')
                ->constrained('integration_customers', 'integration_customer_id')
                ->cascadeOnDelete();

            // Data columns
            $table->string('data_type');
            $table->timestamp('data_timestamp'); // partitioning column
            $table->jsonb('data_payload');
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('data_version')->default(1);

            $table->timestamps();

            // Indexes for querying
            $table->index(['company_id', 'data_type', 'data_timestamp']);
            $table->index(['integration_customer_id', 'data_type', 'data_timestamp']);
        });

        // Convert to hypertable if TimescaleDB is available
        try {
            DB::statement(
                "SELECT create_hypertable('integration_data', 'data_timestamp', if_not_exists => TRUE, chunk_time_interval => interval '1 day');"
            );
        } catch (\Exception $e) {
            // Ignore if not TimescaleDB (for local dev)
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_data');
    }
};
