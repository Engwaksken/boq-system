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
        // BOQs
        Schema::create('boqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('original_language', 10)->default('en');
            $table->string('currency', 10)->default('UGX');
            $table->string('status')->default('draft'); // draft, uploaded, analysed, under_review, approved
            $table->string('source_type')->nullable(); // excel, pdf, scan, manual
            $table->string('source_file_path')->nullable();
            $table->string('version')->default('1.0');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });

        // Facilities
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Bills
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Elements
        Schema::create('elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Sub-elements
        Schema::create('sub_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // BOQ Items
        Schema::create('boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('element_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_element_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code')->nullable();
            $table->text('description');
            $table->string('original_language', 10)->default('en');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('original_rate', 15, 2)->nullable();
            $table->decimal('ai_suggested_rate', 15, 2)->nullable();
            $table->decimal('approved_rate', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->string('work_category')->nullable();
            $table->string('material_category')->nullable();
            $table->string('location')->nullable();
            $table->string('pricing_source')->nullable();
            $table->date('pricing_date')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->decimal('translation_confidence', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, approved, rejected
            $table->timestamps();

            $table->index(['boq_id', 'status']);
            $table->index(['facility_id']);
            $table->index(['bill_id']);
        });

        // BOQ Item Translations
        Schema::create('boq_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('translated_description');
            $table->string('provider')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status')->default('pending_review'); // pending_review, accepted, edited, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('reviewer_comments')->nullable();
            $table->timestamps();

            $table->unique(['boq_item_id', 'locale']);
        });

        // Summaries (bill, facility, grand)
        Schema::create('boq_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary_type'); // bill, facility, grand
            $table->string('name')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat', 15, 2)->default(0);
            $table->decimal('contingency', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_summaries');
        Schema::dropIfExists('boq_item_translations');
        Schema::dropIfExists('boq_items');
        Schema::dropIfExists('sub_elements');
        Schema::dropIfExists('elements');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('boqs');
    }
};
