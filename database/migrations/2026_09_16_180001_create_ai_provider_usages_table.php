<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('boq_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_key', 80)->nullable();
            $table->string('model')->nullable();
            $table->string('operation', 120)->index();
            $table->unsignedBigInteger('input_units')->default(0);
            $table->unsignedBigInteger('output_units')->default(0);
            $table->decimal('estimated_cost', 14, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('successful')->default(false)->index();
            $table->string('error_category', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'provider_key', 'successful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_usages');
    }
};
