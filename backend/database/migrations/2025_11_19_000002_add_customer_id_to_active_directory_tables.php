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
        // Add customer_id to active_directory_users
        Schema::table('active_directory_users', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('integration_id');
            
            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->onDelete('cascade');
            
            $table->index(['company_id', 'customer_id', 'integration_id']);
        });

        // Add customer_id to active_directory_groups
        Schema::table('active_directory_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('integration_id');
            
            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->onDelete('cascade');
            
            $table->index(['company_id', 'customer_id', 'integration_id']);
        });

        // Add customer_id to active_directory_group_memberships
        Schema::table('active_directory_group_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('integration_id');
            
            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->onDelete('cascade');
            
            $table->index(['company_id', 'customer_id', 'integration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_directory_users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['company_id', 'customer_id', 'integration_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('active_directory_groups', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['company_id', 'customer_id', 'integration_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('active_directory_group_memberships', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['company_id', 'customer_id', 'integration_id']);
            $table->dropColumn('customer_id');
        });
    }
};
