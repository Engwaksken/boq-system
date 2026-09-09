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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status'); // pending, active, trial, past_due, grace_period, cancelled, expired, suspended, refunded
            $table->string('access_type'); // one_time, monthly, three_month, six_month, annual, lifetime
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->dateTime('renewal_date')->nullable();
            $table->dateTime('grace_period_end_date')->nullable();
            $table->dateTime('cancellation_date')->nullable();
            $table->boolean('auto_renewal')->default(false);
            $table->string('payment_status')->default('pending');
            $table->string('product_version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
