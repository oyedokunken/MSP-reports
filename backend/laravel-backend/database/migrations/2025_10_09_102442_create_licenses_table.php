<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id('license_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->onDelete('cascade');
            $table->string('license_type');
            $table->string('product_sku');
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->date('expiry_date')->nullable();
            $table->integer('quantity')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
