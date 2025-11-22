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
        Schema::create('exchange_email_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('mailbox_id'); // References exchange_mailboxes.id
            
            $table->foreign('company_id')->references('company_id')->on('companies')->cascadeOnDelete();
            $table->foreign('integration_id')->references('integration_id')->on('integrations')->nullOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            $table->foreign('mailbox_id')->references('id')->on('exchange_mailboxes')->cascadeOnDelete();
            
            // Email address
            $table->string('email_address')->index();
            $table->string('address_type')->default('SMTP'); // SMTP, X400, X500, etc.
            $table->boolean('is_primary')->default(false)->index();
            
            $table->timestamps();
            
            // Unique constraint - one alias per mailbox per customer
            $table->unique(['company_id', 'customer_id', 'mailbox_id', 'email_address'], 'exchange_aliases_unique');
            
            // Index for email lookup
            $table->index(['company_id', 'customer_id', 'email_address']);
            $table->index(['company_id', 'customer_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_email_aliases');
    }
};
