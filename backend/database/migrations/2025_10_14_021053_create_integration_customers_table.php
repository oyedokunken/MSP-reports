<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_customers', function (Blueprint $table) {
            $table->id('integration_customer_id');

            $table->foreignId('integration_id')
                ->constrained('integrations', 'integration_id')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers', 'customer_id')
                ->nullOnDelete();

            $table->string('external_id');
            $table->string('name');
            $table->jsonb('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_customers');
    }
};
