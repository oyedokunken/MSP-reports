<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historical_import_jobs', function (Blueprint $table) {
            $table->id('historical_import_job_id');

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();

            $table->foreignId('integration_id')
                ->constrained('integrations', 'integration_id')
                ->cascadeOnDelete();

            $table->date('import_start_date');
            $table->date('import_end_date')->nullable();
            $table->date('current_position')->nullable();

            $table->enum('status', [
                'queued', 'running', 'paused', 'completed', 'failed'
            ])->default('queued');

            $table->unsignedBigInteger('total_records')->default(0);
            $table->unsignedBigInteger('records_imported')->default(0);
            $table->unsignedInteger('progress_percent')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            $table->unsignedTinyInteger('priority_level')->default(2);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_import_jobs');
    }
};
