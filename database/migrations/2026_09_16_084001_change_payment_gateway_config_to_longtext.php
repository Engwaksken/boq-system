<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE payment_gateways MODIFY config LONGTEXT NULL'
        );
    }

    public function down(): void
    {
        // Intentionally left empty.
        // The config column stores Laravel-encrypted data,
        // which cannot safely be converted back to JSON.
    }
};