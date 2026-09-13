<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->decimal('reviewed_rate', 15, 2)->nullable()->after('ai_suggested_rate');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('approved_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'reviewed_rate',
                'reviewed_at',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
