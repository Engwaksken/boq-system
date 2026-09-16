<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('ai_providers')
            && Schema::hasColumn('ai_providers', 'settings')
        ) {
            DB::statement(
                'ALTER TABLE ai_providers MODIFY settings LONGTEXT NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('ai_providers')
            && Schema::hasColumn('ai_providers', 'settings')
        ) {
            /*
             * Do not automatically convert encrypted ciphertext
             * back to JSON because encrypted data is not valid JSON.
             */
        }
    }
};