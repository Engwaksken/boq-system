<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->longText('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left empty.
        // The config column stores Laravel-encrypted data,
        // which cannot safely be converted back to JSON.
    }
};