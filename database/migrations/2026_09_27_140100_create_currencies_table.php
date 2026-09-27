<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });

        $now = now();
        $default = DB::table('site_settings')->where('key', 'currency')->value('value') ?: 'UGX';

        $currencies = [
            ['UGX', 'Ugandan Shilling', 'USh', 0],
            ['USD', 'US Dollar', '$', 2],
            ['KES', 'Kenyan Shilling', 'KSh', 2],
            ['TZS', 'Tanzanian Shilling', 'TSh', 0],
            ['RWF', 'Rwandan Franc', 'FRw', 0],
            ['SSP', 'South Sudanese Pound', 'SSP', 2],
            ['CDF', 'Congolese Franc', 'FC', 2],
            ['EUR', 'Euro', '€', 2],
            ['GBP', 'British Pound', '£', 2],
        ];

        foreach ($currencies as $index => [$code, $name, $symbol, $decimals]) {
            DB::table('currencies')->insert([
                'code' => $code,
                'name' => $name,
                'symbol' => $symbol,
                'decimal_places' => $decimals,
                'is_active' => true,
                'is_default' => strtoupper((string) $default) === $code,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
