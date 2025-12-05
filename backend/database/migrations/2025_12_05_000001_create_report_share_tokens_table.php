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
        Schema::create('report_share_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_report_id');
            $table->unsignedBigInteger('company_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('generated_report_id')
                ->references('generated_report_id')
                ->on('generated_reports')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('company_id')
                ->on('companies')
                ->onDelete('cascade');

            $table->index(['token', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_share_tokens');
    }
};
