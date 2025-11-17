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
        Schema::create('active_directory_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            
            // Group reference
            $table->string('group_name');
            $table->text('group_dn');
            
            // User reference
            $table->string('user_sam_account_name');
            $table->text('user_dn');
            
            // Membership type
            $table->string('membership_type')->default('Direct'); // Direct or Nested
            
            $table->timestamps();
            
            // Indexes for queries
            $table->index(['company_id', 'group_name']);
            $table->index(['company_id', 'user_sam_account_name']);
            
            // Unique constraint to prevent duplicate memberships
            $table->unique(['company_id', 'group_dn', 'user_dn'], 'ad_memberships_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_directory_group_memberships');
    }
};
