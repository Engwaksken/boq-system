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
        Schema::table('boq_items', function (Blueprint $table) {
            $table->enum('pricing_status', [
                'unpriced',
                'pricing',
                'priced',
                'failed',
                'skipped'
            ])->default('unpriced')->after('status');
            $table->timestamp('priced_at')->nullable()->after('pricing_status');
            $table->text('pricing_error')->nullable()->after('priced_at');
            $table->unsignedInteger('batch_number')->nullable()->after('pricing_error');
            $table->foreignId('pricing_job_id')->nullable()->constrained('boq_pricing_jobs')->nullOnDelete()->after('batch_number');

            // Indexes for performance
            $table->index(['boq_id', 'pricing_status']);
            $table->index(['pricing_job_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->dropIndex(['boq_id', 'pricing_status']);
            $table->dropIndex(['pricing_job_id']);
            $table->dropForeign(['pricing_job_id']);
            $table->dropColumn([
                'pricing_status',
                'priced_at',
                'pricing_error',
                'batch_number',
                'pricing_job_id',
            ]);
        });
    }
};