<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id('alert_rule_id');
            
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('severity', ['critical', 'warning', 'info'])->default('warning');
            $table->enum('metric_type', [
                'database_connectivity',
                'disk_space',
                'integration_sync_failure',
                'report_generation_failure',
                'queue_depth',
                'rate_limit_hit',
                'security_event',
                'slow_query'
            ]);
            
            $table->jsonb('condition'); // {operator: '>', threshold: 10, period: '1h'}
            $table->jsonb('notification_channels');
            $table->jsonb('notification_config');
            
            $table->integer('cooldown_minutes')->default(60);
            $table->timestamp('last_triggered_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['company_id', 'is_active']);
            $table->index(['metric_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
