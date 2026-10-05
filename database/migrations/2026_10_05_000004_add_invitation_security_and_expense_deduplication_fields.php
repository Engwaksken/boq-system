<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->timestamp('consumed_at')->nullable()->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->char('deduplication_hash', 64)->nullable();
            // Duplicate expenses are scoped per organisation, so identical purchases
            // in different tenants never collide.
            $table->unique(['organisation_id', 'deduplication_hash'], 'expenses_org_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_org_dedup_unique');
            $table->dropColumn('deduplication_hash');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['consumed_at']);
            $table->dropColumn(['consumed_at', 'attempt_count', 'last_attempt_at']);
        });
    }
};
