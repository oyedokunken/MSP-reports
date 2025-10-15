<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id('generated_report_id');

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers', 'customer_id')
                ->cascadeOnDelete();

            $table->foreignId('report_schedule_id')
                ->constrained('report_schedules', 'report_schedule_id')
                ->cascadeOnDelete();

            $table->timestamp('generated_at')->nullable();
            $table->jsonb('report_period')->nullable();
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->enum('delivery_method', ['email','api','download'])->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('retention_until')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
