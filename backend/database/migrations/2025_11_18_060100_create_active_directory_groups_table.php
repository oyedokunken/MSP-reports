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
        Schema::create('active_directory_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            
            // Group identity
            $table->string('name')->index();
            $table->string('sam_account_name')->index();
            
            // Group properties
            $table->string('group_category'); // Security or Distribution
            $table->string('group_scope'); // Global, Universal, Domain Local
            $table->text('description')->nullable();
            
            // AD metadata
            $table->timestamp('created_at_ad')->nullable();
            $table->timestamp('modified_at_ad')->nullable();
            $table->text('distinguished_name')->nullable();
            
            // Management
            $table->string('managed_by')->nullable();
            $table->text('managed_by_dn')->nullable();
            
            $table->timestamps();
            
            // Unique constraint on company + DN
            $table->unique(['company_id', 'distinguished_name'], 'ad_groups_company_dn_unique');
            
            // Additional indexes
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'sam_account_name']);
            $table->index(['company_id', 'group_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_directory_groups');
    }
};
