<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->foreignId('company_id')
                ->constrained('companies', 'company_id')
                ->onDelete('cascade');
            $table->foreignId('role_id')
                ->constrained('roles', 'id')
                ->onDelete('restrict');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive', 'locked'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->enum('auth_method', ['local', 'saml', 'm365', 'ldap'])->default('local');
            $table->jsonb('permissions')->nullable();
            $table->timestamps();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
