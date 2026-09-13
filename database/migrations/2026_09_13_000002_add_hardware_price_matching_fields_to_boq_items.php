<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->foreignId('hardware_price_id')->nullable()->after('ai_suggested_rate')->constrained()->nullOnDelete();
            $table->string('match_type')->nullable()->after('hardware_price_id');
            $table->foreignId('matched_by')->nullable()->after('match_type')->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable()->after('matched_by');
        });
    }

    public function down(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hardware_price_id');
            $table->dropConstrainedForeignId('matched_by');
            $table->dropColumn(['match_type', 'matched_at']);
        });
    }
};
