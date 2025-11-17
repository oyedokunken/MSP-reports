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
        Schema::create('active_directory_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            
            // Core identity
            $table->string('user_principal_name')->index();
            $table->string('sam_account_name')->index();
            $table->string('display_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->nullable()->index();
            
            // Organizational
            $table->string('department')->nullable();
            $table->string('title')->nullable();
            $table->string('manager')->nullable();
            $table->text('manager_dn')->nullable();
            
            // Account status
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_logon')->nullable();
            $table->timestamp('password_last_set')->nullable();
            $table->boolean('password_never_expires')->default(false);
            $table->timestamp('account_expiration')->nullable();
            $table->boolean('locked_out')->default(false);
            
            // AD metadata
            $table->timestamp('created_at_ad')->nullable();
            $table->timestamp('modified_at_ad')->nullable();
            $table->text('distinguished_name')->nullable();
            $table->text('description')->nullable();
            
            // Contact information
            $table->string('office')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('company')->nullable();
            
            $table->timestamps();
            
            // Unique constraint on company + UPN
            $table->unique(['company_id', 'user_principal_name'], 'ad_users_company_upn_unique');
            
            // Additional indexes
            $table->index(['company_id', 'enabled']);
            $table->index(['company_id', 'sam_account_name']);
            $table->index(['company_id', 'department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_directory_users');
    }
};
