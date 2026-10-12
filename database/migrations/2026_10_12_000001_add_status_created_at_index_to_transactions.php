<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payments:reconcile runs every five minutes and filters transactions by status and
     * created_at (pending within 48h, and stale pending to abandon). Without this index
     * both queries scan the whole table.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'transactions_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_status_created_at_index');
        });
    }
};
