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
        Schema::create('active_directory_license_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('integration_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('user_sam_account_name');
            $table->string('user_dn')->nullable();
            $table->string('license_name');
            $table->string('license_type')->default('SPLA');
            $table->decimal('cost_per_user', 10, 2)->default(0);
            $table->string('assigned_via_group');
            $table->string('assigned_via_group_dn')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            $table->foreign('integration_id')->references('integration_id')->on('integrations')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');

            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'license_name']);
            $table->index(['company_id', 'license_type']);
            $table->index('user_sam_account_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_directory_license_assignments');
    }
};
