<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_mappings', function (Blueprint $table) {
            $table->id('customer_mapping_id');

            $table->foreignId('customer_id')
                ->constrained('customers', 'customer_id')
                ->cascadeOnDelete();
            $table->foreignId('integration_customer_id')
                ->constrained('integration_customers', 'integration_customer_id')
                ->cascadeOnDelete();
            $table->foreignId('mapped_by_user_id')->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();

            $table->enum('mapping_type', ['auto', 'manual'])->default('auto');
            $table->unsignedTinyInteger('confidence_score')->nullable(); // 0-100 for auto
            $table->timestamp('mapped_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_mappings');
    }
};
