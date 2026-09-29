<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved signature the user can reuse on any BOQ in one click.
 */
return new class extends Migration
{
    private const COLUMNS = ['signature_path', 'signature_method', 'signature_name', 'signature_title'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'signature_path')) {
                $table->string('signature_path')->nullable();
            }
            if (! Schema::hasColumn('users', 'signature_method')) {
                $table->string('signature_method', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'signature_name')) {
                $table->string('signature_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'signature_title')) {
                $table->string('signature_title')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('users', $column)));

        if ($columns !== []) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
