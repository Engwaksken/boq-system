<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['organisation_id', 'action', 'created_at'], 'audit_logs_mcp_activity_index');
        });
        Schema::table('price_histories', function (Blueprint $table) {
            $table->index(['organisation_id', 'supplier', 'recorded_at'], 'price_histories_supplier_recorded_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex('audit_logs_mcp_activity_index'));
        Schema::table('price_histories', fn (Blueprint $table) => $table->dropIndex('price_histories_supplier_recorded_index'));
    }
};
