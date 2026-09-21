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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('region')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->json('materials')->nullable();
            $table->json('name_translations')->nullable();
            $table->json('notes_translations')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->string('preferred_language', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('item');
            $table->text('description')->nullable();
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->string('original_language', 5)->default('en');
            $table->string('category')->nullable();
            $table->string('unit');
            $table->decimal('rate', 18, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type'); // previous_boq, supplier_quotation, supplier_price_list, procurement, market_survey, reference_schedule, external_feed, manual
            $table->string('source_reference')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->date('review_required_at')->nullable();
            $table->string('verification_status')->default('pending'); // draft, pending, approved, rejected, expired
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'unit']);
            $table->index(['region', 'currency']);
            $table->index(['verification_status', 'is_active']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('boq_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, sent, received, reviewed, accepted, rejected, expired
            $table->date('quotation_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->decimal('exchange_rate', 18, 4)->nullable();
            $table->string('exchange_rate_source', 30)->nullable(); // live_market, project_contract, manual
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('source')->default('manual'); // pdf, excel, scanned, camera, manual, api
            $table->string('source_file_name')->nullable();
            $table->string('source_language', 5)->default('en');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('valid_until');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('product');
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 18, 3)->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->boolean('matched')->default(false);
            $table->foreignId('matched_rate_id')->nullable()->constrained('rates')->nullOnDelete();
            $table->boolean('approved')->default(false);
            $table->string('source_item_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('suppliers');
    }
};