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
        Schema::create('exchange_mailbox_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('mailbox_id'); // References exchange_mailboxes.id
            
            $table->foreign('company_id')->references('company_id')->on('companies')->cascadeOnDelete();
            $table->foreign('integration_id')->references('integration_id')->on('integrations')->nullOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            $table->foreign('mailbox_id')->references('id')->on('exchange_mailboxes')->cascadeOnDelete();
            
            // Permission details
            $table->string('permission_type')->index(); // FullAccess, SendAs, SendOnBehalf
            $table->string('user_identity'); // Who has the permission
            $table->string('user_sam_account_name')->nullable()->index();
            $table->string('user_display_name')->nullable();
            
            // Access rights (for FullAccess type)
            $table->json('access_rights')->nullable(); // ['FullAccess', 'ReadPermission', etc.]
            
            // Inheritance and deny
            $table->boolean('inherited')->default(false);
            $table->boolean('deny')->default(false);
            
            // Automapping (for FullAccess)
            $table->boolean('automapping')->default(true);
            
            $table->timestamps();
            
            // Prevent duplicate permissions
            $table->unique([
                'company_id', 
                'customer_id', 
                'mailbox_id', 
                'permission_type', 
                'user_identity'
            ], 'exchange_permissions_unique');
            
            // Indexes for queries
            $table->index(['company_id', 'customer_id', 'permission_type']);
            $table->index(['company_id', 'customer_id', 'user_sam_account_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_mailbox_permissions');
    }
};
