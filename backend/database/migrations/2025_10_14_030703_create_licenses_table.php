<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id('license_id');

            $table->enum('license_type', ['integration', 'retention', 'users', 'reports']);
            $table->string('product_sku')->unique();
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->dateTime('expiry_date')->nullable();
            $table->integer('quantity')->nullable(); // For user-based licenses
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
