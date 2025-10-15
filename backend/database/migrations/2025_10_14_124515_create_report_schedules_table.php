<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id('report_schedule_id');

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers', 'customer_id')
                ->cascadeOnDelete();

            $table->string('report_type');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly', 'on-demand']);
            $table->jsonb('schedule_config')->nullable();
            $table->time('time_of_day')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_scheduled_at')->nullable();
            $table->string('timezone')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
