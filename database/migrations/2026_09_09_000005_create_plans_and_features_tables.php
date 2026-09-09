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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->string('type'); // one_time, monthly, three_month, six_month, annual, lifetime
            $table->unsignedInteger('duration_days')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->boolean('has_trial')->default(false);
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_projects')->nullable();
            $table->unsignedInteger('max_boqs')->nullable();
            $table->unsignedBigInteger('max_storage_bytes')->nullable();
            $table->unsignedInteger('max_ai_credits')->nullable();
            $table->unsignedInteger('max_ocr_pages')->nullable();
            $table->unsignedInteger('max_translations')->nullable();
            $table->json('included_features')->nullable();
            $table->json('included_updates')->nullable();
            $table->boolean('feature_update_eligible')->default(false);
            $table->boolean('auto_renewal')->default(false);
            $table->unsignedInteger('grace_period_days')->default(0);
            $table->string('refund_policy')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->string('module')->nullable();
            $table->string('version_introduced')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_topup')->default(false);
            $table->json('usage_limits')->nullable();
            $table->json('permission_requirements')->nullable();
            $table->timestamps();
        });

        // Pivot: plan_feature
        Schema::create('plan_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->json('limits')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_feature');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
    }
};
