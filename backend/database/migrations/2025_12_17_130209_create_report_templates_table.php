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
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id('template_id');
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
            
            // Template identification
            $table->string('template_name');
            $table->text('description')->nullable();
            
            // Report configuration
            $table->string('report_type');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly', 'on-demand']);
            $table->jsonb('schedule_config')->nullable();
            $table->time('time_of_day')->nullable();
            $table->string('timezone')->default('Australia/Brisbane');
            
            // Report customization
            $table->jsonb('included_sections')->nullable();
            $table->jsonb('delivery_settings')->nullable();
            $table->jsonb('report_options')->nullable();
            
            // Status
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['company_id', 'active']);
            $table->index('report_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
