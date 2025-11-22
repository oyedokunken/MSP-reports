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
        Schema::create('exchange_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            
            $table->foreign('company_id')->references('company_id')->on('companies')->cascadeOnDelete();
            $table->foreign('integration_id')->references('integration_id')->on('integrations')->nullOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            
            // Identity
            $table->string('guid')->index(); // Exchange GUID
            $table->string('identity'); // Full Exchange identity
            $table->string('display_name')->index();
            $table->string('alias')->nullable()->index();
            $table->string('primary_smtp_address')->index();
            $table->string('user_principal_name')->nullable()->index();
            $table->string('sam_account_name')->nullable()->index(); // Link to AD
            
            // Mailbox type and properties
            $table->string('recipient_type'); // UserMailbox, SharedMailbox, RoomMailbox, etc.
            $table->string('recipient_type_details'); // Detailed type
            $table->boolean('is_shared')->default(false)->index();
            $table->boolean('is_resource')->default(false)->index(); // Room/Equipment
            $table->string('resource_type')->nullable(); // Room, Equipment
            
            // Status
            $table->boolean('hidden_from_address_lists')->default(false);
            $table->string('external_directory_object_id')->nullable();
            
            // Database and location
            $table->string('database_name')->nullable()->index();
            $table->string('server_name')->nullable();
            $table->text('distinguished_name')->nullable();
            
            // Licensing (for Microsoft 365/Exchange Online)
            $table->string('license_type')->nullable(); // Exchange Online Plan 1, Plan 2, etc.
            $table->json('assigned_licenses')->nullable(); // Array of license SKUs
            
            // Quotas (in MB for consistency)
            $table->unsignedBigInteger('prohibit_send_quota_mb')->nullable();
            $table->unsignedBigInteger('prohibit_send_receive_quota_mb')->nullable();
            $table->unsignedBigInteger('issue_warning_quota_mb')->nullable();
            $table->boolean('use_database_quota_defaults')->default(true);
            
            // Current usage (updated from statistics)
            $table->unsignedBigInteger('total_item_size_mb')->nullable()->index();
            $table->unsignedBigInteger('total_deleted_item_size_mb')->nullable();
            $table->unsignedInteger('item_count')->nullable();
            $table->unsignedInteger('deleted_item_count')->nullable();
            $table->timestamp('last_logon_time')->nullable()->index();
            $table->timestamp('last_logoff_time')->nullable();
            
            // Archive mailbox
            $table->boolean('archive_enabled')->default(false)->index();
            $table->string('archive_guid')->nullable();
            $table->string('archive_name')->nullable();
            $table->string('archive_database')->nullable();
            $table->unsignedBigInteger('archive_quota_mb')->nullable();
            $table->unsignedBigInteger('archive_warning_quota_mb')->nullable();
            $table->unsignedBigInteger('archive_size_mb')->nullable();
            
            // Litigation hold and retention
            $table->boolean('litigation_hold_enabled')->default(false)->index();
            $table->timestamp('litigation_hold_date')->nullable();
            $table->string('litigation_hold_owner')->nullable();
            $table->string('retention_policy')->nullable();
            $table->text('retention_comment')->nullable();
            $table->string('retention_url')->nullable();
            
            // Forwarding
            $table->string('forwarding_address')->nullable();
            $table->string('forwarding_smtp_address')->nullable();
            $table->boolean('deliver_to_mailbox_and_forward')->default(false);
            
            // Organizational
            $table->string('organizational_unit')->nullable()->index();
            $table->string('office')->nullable();
            $table->string('department')->nullable()->index();
            $table->string('company')->nullable();
            $table->string('custom_attribute_1')->nullable();
            $table->string('custom_attribute_2')->nullable();
            $table->string('custom_attribute_3')->nullable();
            
            // Exchange metadata
            $table->timestamp('when_created')->nullable();
            $table->timestamp('when_changed')->nullable()->index();
            $table->string('exchange_version')->nullable();
            
            $table->timestamps();
            
            // Unique constraints per customer
            $table->unique(['company_id', 'customer_id', 'guid'], 'exchange_mailboxes_unique_guid');
            $table->unique(['company_id', 'customer_id', 'primary_smtp_address'], 'exchange_mailboxes_unique_smtp');
            
            // Composite indexes for common queries
            $table->index(['company_id', 'customer_id', 'integration_id']);
            $table->index(['company_id', 'customer_id', 'recipient_type']);
            $table->index(['company_id', 'customer_id', 'is_shared']);
            $table->index(['company_id', 'customer_id', 'database_name']);
            $table->index(['company_id', 'customer_id', 'archive_enabled']);
            $table->index(['company_id', 'customer_id', 'total_item_size_mb']); // For capacity reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_mailboxes');
    }
};
