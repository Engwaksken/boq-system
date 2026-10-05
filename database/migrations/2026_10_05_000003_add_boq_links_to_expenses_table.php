<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('boq_id')->nullable()->after('project_id')->constrained('boqs')->nullOnDelete();
            $table->foreignId('boq_item_id')->nullable()->after('boq_id')->constrained('boq_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boq_item_id');
            $table->dropConstrainedForeignId('boq_id');
        });
    }
};
