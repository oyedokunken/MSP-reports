<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_retention_logs', function (Blueprint $table) {
            $table->id('data_retention_log_id');

            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->cascadeOnDelete();

            $table->string('record_type'); 
            $table->unsignedBigInteger('records_purged')->default(0);
            $table->jsonb('retention_policy')->nullable();
            $table->timestamp('purged_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_logs');
    }
};
