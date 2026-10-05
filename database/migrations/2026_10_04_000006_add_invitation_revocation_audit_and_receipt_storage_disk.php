<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('revoked_by_user_id')
                ->nullable()
                ->after('revoked_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('expense_receipts', function (Blueprint $table) {
            $table->string('storage_disk')->default('private')->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('expense_receipts', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by_user_id');
        });
    }
};
