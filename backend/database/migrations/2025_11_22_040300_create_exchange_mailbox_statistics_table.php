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
        Schema::create('exchange_mailbox_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('mailbox_id'); // References exchange_mailboxes.id
            
            $table->foreign('company_id')->references('company_id')->on('companies')->cascadeOnDelete();
            $table->foreign('integration_id')->references('integration_id')->on('integrations')->nullOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            $table->foreign('mailbox_id')->references('id')->on('exchange_mailboxes')->cascadeOnDelete();
            
            // Size statistics (in MB)
            $table->unsignedBigInteger('total_item_size_mb')->index();
            $table->unsignedBigInteger('total_deleted_item_size_mb')->nullable();
            
            // Item counts
            $table->unsignedInteger('item_count')->index();
            $table->unsignedInteger('deleted_item_count')->nullable();
            $table->unsignedInteger('associated_item_count')->nullable(); // Calendar, Contacts, etc.
            
            // Folder statistics
            $table->unsignedInteger('folder_count')->nullable();
            
            // Database and storage
            $table->string('database_name')->nullable()->index();
            $table->string('database_guid')->nullable();
            $table->boolean('is_archival')->default(false);
            
            // Usage timestamps
            $table->timestamp('last_logon_time')->nullable()->index();
            $table->timestamp('last_logoff_time')->nullable();
            $table->string('last_logon_user_account')->nullable();
            $table->string('last_logged_on_user_account')->nullable();
            
            // Timestamp when these statistics were collected
            $table->timestamp('statistics_timestamp')->index();
            
            $table->timestamps();
            
            // Indexes for time-series queries
            $table->index(['company_id', 'customer_id', 'mailbox_id', 'statistics_timestamp']);
            $table->index(['company_id', 'customer_id', 'statistics_timestamp']);
            $table->index(['company_id', 'customer_id', 'database_name', 'statistics_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_mailbox_statistics');
    }
};
