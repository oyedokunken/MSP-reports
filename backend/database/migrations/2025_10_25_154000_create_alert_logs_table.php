<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id('alert_log_id');
            
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
            
            $table->foreignId('alert_rule_id')
                ->nullable()
                ->constrained('alert_rules', 'alert_rule_id')
                ->nullOnDelete();
            
            $table->enum('severity', ['critical', 'warning', 'info']);
            $table->string('alert_type');
            $table->string('title');
            $table->text('message');
            $table->jsonb('context')->nullable();
            
            $table->jsonb('notification_status');
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['company_id', 'triggered_at']);
            $table->index(['severity', 'triggered_at']);
            $table->index(['acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
