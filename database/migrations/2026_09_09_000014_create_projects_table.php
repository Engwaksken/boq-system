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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('client')->nullable();
            $table->string('contractor')->nullable();
            $table->string('consultant')->nullable();
            $table->string('quantity_surveyor')->nullable();
            $table->string('project_manager')->nullable();
            $table->string('site_engineer')->nullable();
            $table->string('funding_organisation')->nullable();
            $table->string('country')->nullable();
            $table->string('district')->nullable();
            $table->string('location')->nullable();
            $table->string('project_type')->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('currency', 10)->default('UGX');
            $table->text('description')->nullable();
            $table->string('original_language', 10)->default('en');
            $table->string('report_language', 10)->default('en');
            $table->string('status')->default('draft'); // draft, active, completed, archived
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
