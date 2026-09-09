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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('locale', 10)->default('en')->after('password');
            $table->string('timezone', 64)->nullable()->after('locale');
            $table->string('phone')->nullable()->after('timezone');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['locale', 'timezone', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
