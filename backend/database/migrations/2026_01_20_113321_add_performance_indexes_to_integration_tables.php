<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance optimization indexes for integration-related tables.
 * These indexes are based on common query patterns identified in the codebase.
 */
return new class extends Migration
{
    /**
     * Check if an index exists (PostgreSQL)
     */
    private function indexExists(string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                "SELECT EXISTS (
                    SELECT 1 FROM pg_indexes WHERE indexname = ?
                ) as exists",
                [$indexName]
            );
            return (bool) ($result->exists ?? false);
        }

        if ($driver === 'sqlite') {
            $result = DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?",
                [$indexName]
            );
            return $result !== null;
        }

        // Default to checking schema (slower but more compatible)
        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index for integration_data lookups by type and customer
        // Used heavily in reports and data gathering
        Schema::table('integration_data', function (Blueprint $table) {
            // Composite index for common query: WHERE integration_id = ? AND data_type = ? AND integration_customer_id = ?
            if (!$this->indexExists('idx_integration_data_type_customer')) {
                $table->index(
                    ['integration_id', 'data_type', 'integration_customer_id'],
                    'idx_integration_data_type_customer'
                );
            }

            // Index for external_id lookups (used in bulk upserts)
            if (!$this->indexExists('idx_integration_data_external')) {
                $table->index(
                    ['integration_id', 'data_type', 'external_id'],
                    'idx_integration_data_external'
                );
            }

            // Index for timestamp-based queries (reports, historical data)
            if (!$this->indexExists('idx_integration_data_timestamp')) {
                $table->index(
                    ['integration_id', 'data_type', 'data_timestamp'],
                    'idx_integration_data_timestamp'
                );
            }

            // Index for company-wide queries (exports, multi-tenant isolation)
            if (!$this->indexExists('idx_integration_data_company_type')) {
                $table->index(
                    ['company_id', 'data_type'],
                    'idx_integration_data_company_type'
                );
            }
        });

        // Index for sync_logs status checks
        Schema::table('sync_logs', function (Blueprint $table) {
            // Index for finding running/stale syncs
            if (!$this->indexExists('idx_sync_logs_status_started')) {
                $table->index(
                    ['status', 'started_at'],
                    'idx_sync_logs_status_started'
                );
            }

            // Index for integration-specific sync history
            if (!$this->indexExists('idx_sync_logs_integration_status')) {
                $table->index(
                    ['integration_id', 'status', 'created_at'],
                    'idx_sync_logs_integration_status'
                );
            }
        });

        // Index for integration_customers lookups
        Schema::table('integration_customers', function (Blueprint $table) {
            // Index for external_id lookups (very common)
            if (!$this->indexExists('idx_integration_customers_external') && !$this->indexExists('idx_integration_customers_external_unique')) {
                $table->index(
                    ['integration_id', 'external_id'],
                    'idx_integration_customers_external'
                );
            }

            // Index for unmapped customer queries
            if (!$this->indexExists('idx_integration_customers_mapping')) {
                $table->index(
                    ['integration_id', 'customer_id'],
                    'idx_integration_customers_mapping'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_data', function (Blueprint $table) {
            if ($this->indexExists('idx_integration_data_type_customer')) {
                $table->dropIndex('idx_integration_data_type_customer');
            }
            if ($this->indexExists('idx_integration_data_external')) {
                $table->dropIndex('idx_integration_data_external');
            }
            if ($this->indexExists('idx_integration_data_timestamp')) {
                $table->dropIndex('idx_integration_data_timestamp');
            }
            if ($this->indexExists('idx_integration_data_company_type')) {
                $table->dropIndex('idx_integration_data_company_type');
            }
        });

        Schema::table('sync_logs', function (Blueprint $table) {
            if ($this->indexExists('idx_sync_logs_status_started')) {
                $table->dropIndex('idx_sync_logs_status_started');
            }
            if ($this->indexExists('idx_sync_logs_integration_status')) {
                $table->dropIndex('idx_sync_logs_integration_status');
            }
        });

        Schema::table('integration_customers', function (Blueprint $table) {
            if ($this->indexExists('idx_integration_customers_external')) {
                $table->dropIndex('idx_integration_customers_external');
            }
            if ($this->indexExists('idx_integration_customers_mapping')) {
                $table->dropIndex('idx_integration_customers_mapping');
            }
        });
    }
};
