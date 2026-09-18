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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('payer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('beneficiary_id')
                ->nullable()
                ->after('payer_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('payer_id');
            $table->index('beneficiary_id');
            $table->index(['user_id', 'beneficiary_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'beneficiary_id']);
            $table->dropIndex('beneficiary_id');
            $table->dropIndex('payer_id');
            $table->dropForeign(['beneficiary_id']);
            $table->dropForeign(['payer_id']);
            $table->dropColumn(['payer_id', 'beneficiary_id']);
        });
    }
};