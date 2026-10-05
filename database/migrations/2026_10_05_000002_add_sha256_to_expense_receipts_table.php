<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_receipts') && ! Schema::hasColumn('expense_receipts', 'sha256')) {
            Schema::table('expense_receipts', function (Blueprint $table) {
                $table->char('sha256', 64)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_receipts') && Schema::hasColumn('expense_receipts', 'sha256')) {
            Schema::table('expense_receipts', function (Blueprint $table) {
                $table->dropIndex(['sha256']);
                $table->dropColumn('sha256');
            });
        }
    }
};
