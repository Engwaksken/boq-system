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
        Schema::create('boq_pricing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location');
            $table->enum('status', [
                'queued',
                'processing',
                'paused',
                'completed',
                'completed_with_errors',
                'failed',
                'cancelled'
            ])->default('queued');
            $table->unsignedInteger('current_batch')->default(0);
            $table->unsignedInteger('total_batches')->default(0);
            $table->unsignedInteger('batch_size')->default(20);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['boq_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['locked_at', 'locked_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_pricing_jobs');
    }
};