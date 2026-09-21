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
        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->string('type'); // feature_unlock, version_update, bundle, ai_credit_topup, ocr_credit_topup, translation_credit_topup, storage_topup, user_seat_topup, project_limit_topup, boq_limit_topup, report_export_topup
            $table->decimal('price', 14, 2)->default(0);
            $table->string('currency', 3)->default('UGX');
            $table->integer('duration_days')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->string('release_version')->nullable();
            $table->json('included_features')->nullable();
            $table->json('usage_credits')->nullable();
            $table->json('limits')->nullable();
            $table->json('applicable_plans')->nullable(); // [] or null = all plans; otherwise plan codes
            $table->integer('purchase_limit')->nullable();
            $table->boolean('requires_confirmation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('topup_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, active, expired, revoked
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->string('version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['organisation_id', 'status']);
            $table->index(['topup_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topup_purchases');
        Schema::dropIfExists('topups');
    }
};