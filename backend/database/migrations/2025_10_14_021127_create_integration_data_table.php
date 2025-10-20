<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_data', function (Blueprint $table) {
            $table->unsignedBigInteger('integration_data_id');
            $table->timestamp('data_timestamp'); // partitioning column
            $table->primary(['integration_data_id', 'data_timestamp']);

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

        try {
            DB::statement(
                "SELECT create_hypertable('integration_data', 'data_timestamp', if_not_exists => TRUE, chunk_time_interval => interval '1 day');"
            );
        } catch (\Exception $e) {
            dd('TimescaleDB hypertable creation failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_data');
    }
};
