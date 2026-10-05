<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Five-digit codes live in a 100 000-value space and must be reusable once an
        // invitation is consumed or expires. Replace the permanent unique key with a
        // plain index; active-window uniqueness is enforced in InvitationService.
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropUnique('invitations_token_hash_unique');
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['token_hash']);
            $table->unique('token_hash');
        });
    }
};
