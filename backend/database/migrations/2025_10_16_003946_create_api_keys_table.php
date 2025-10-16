<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id('api_key_id');
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->onDelete('cascade');
            $table->foreignId('created_by_user_id')
                ->constrained('users', 'user_id')
                ->onDelete('set null')
                ->nullable();
            $table->string('display_name');
            $table->string('key_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->jsonb('permissions')->nullable();
            $table->jsonb('ip_whitelist')->nullable();
            $table->jsonb('allowed_endpoints')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
