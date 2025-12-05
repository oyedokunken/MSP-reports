<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_data', function (Blueprint $table) {
            $table->id('integration_data_id');
            $table->timestamp('data_timestamp'); // partitioning column

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
            $table->foreignId('integration_id')
                ->constrained('integrations', 'integration_id')
                ->cascadeOnDelete();
            $table->foreignId('integration_customer_id')
                ->constrained('integration_customers', 'integration_customer_id')
                ->cascadeOnDelete();

            $table->string('data_type');
            $table->jsonb('data_payload');
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('data_version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'data_type', 'data_timestamp']);
            $table->index(['integration_customer_id', 'data_type', 'data_timestamp']);
        });

        // TimescaleDB-specific commands (skip for SQLite)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            try {
                // Drop the default primary key created by id()
                DB::statement('ALTER TABLE integration_data DROP CONSTRAINT integration_data_pkey');
                
                // Create hypertable
                DB::statement(
                    "SELECT create_hypertable('integration_data', 'data_timestamp', if_not_exists => TRUE, chunk_time_interval => interval '1 day');"
                );
                
                // Add composite primary key required by TimescaleDB
                DB::statement('ALTER TABLE integration_data ADD PRIMARY KEY (integration_data_id, data_timestamp)');
            } catch (\Exception $e) {
                \Log::warning('TimescaleDB hypertable creation failed: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_data');
    }
};
