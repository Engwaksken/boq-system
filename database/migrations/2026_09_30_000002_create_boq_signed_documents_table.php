<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scans or PDFs of the physically signed BOQ, kept as private records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boq_signed_documents')) {
            return;
        }

        Schema::create('boq_signed_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_signed_documents');
    }
};
