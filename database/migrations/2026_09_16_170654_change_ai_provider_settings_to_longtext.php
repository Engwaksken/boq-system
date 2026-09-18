<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_providers') && Schema::hasColumn('ai_providers', 'settings')) {
            Schema::table('ai_providers', function (Blueprint $table) {
                $table->longText('settings')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        /*
         * Do not convert encrypted ciphertext back to JSON.
         */
    }
};