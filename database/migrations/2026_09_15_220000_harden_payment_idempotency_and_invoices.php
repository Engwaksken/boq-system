<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->after('reference');
            $table->unique('idempotency_key', 'transactions_idempotency_key_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('transaction_id', 'invoices_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_transaction_id_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
