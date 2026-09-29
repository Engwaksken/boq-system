<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets admins track each AI provider's credit, token allowance and expiry so
     * they can top up before it runs out.
     */
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table): void {
            $columns = [
                'credit_balance' => fn () => $table->decimal('credit_balance', 14, 4)->nullable(),
                'credit_currency' => fn () => $table->string('credit_currency', 8)->nullable(),
                'credit_expires_at' => fn () => $table->date('credit_expires_at')->nullable(),
                'monthly_token_limit' => fn () => $table->unsignedBigInteger('monthly_token_limit')->nullable(),
                'low_credit_threshold' => fn () => $table->decimal('low_credit_threshold', 14, 4)->nullable(),
                'balance_checked_at' => fn () => $table->timestamp('balance_checked_at')->nullable(),
                'credit_exhausted_at' => fn () => $table->timestamp('credit_exhausted_at')->nullable(),
                'last_credit_alert_at' => fn () => $table->timestamp('last_credit_alert_at')->nullable(),
            ];

            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('ai_providers', $name)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table): void {
            foreach ([
                'credit_balance', 'credit_currency', 'credit_expires_at', 'monthly_token_limit',
                'low_credit_threshold', 'balance_checked_at', 'credit_exhausted_at', 'last_credit_alert_at',
            ] as $name) {
                if (Schema::hasColumn('ai_providers', $name)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};
