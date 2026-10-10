<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings validate the default language against this table, so make sure
     * the built-in languages exist even when seeders were never run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        $now = now();
        $hasDefault = DB::table('languages')->where('is_default', true)->exists();

        foreach ([['en', 'English', 'English', 'Y-m-d'], ['lg', 'Luganda', 'Luganda', 'd/m/Y'], ['sw', 'Swahili', 'Kiswahili', 'd/m/Y']] as [$code, $name, $native, $dateFormat]) {
            if (DB::table('languages')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('languages')->insert([
                'code' => $code,
                'name' => $name,
                'native_name' => $native,
                'direction' => 'ltr',
                'date_format' => $dateFormat,
                'is_active' => true,
                'is_default' => ! $hasDefault && $code === 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Languages may be referenced by users and projects; nothing is removed.
    }
};
