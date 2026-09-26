<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'hardware_prices',
            function (Blueprint $table): void {
                if (
                    ! Schema::hasColumn(
                        'hardware_prices',
                        'price_type'
                    )
                ) {
                    $table
                        ->string(
                            'price_type',
                            20
                        )
                        ->default('hardware')
                        ->after('category')
                        ->index();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'hardware_prices',
            function (Blueprint $table): void {
                if (
                    Schema::hasColumn(
                        'hardware_prices',
                        'price_type'
                    )
                ) {
                    $table->dropColumn(
                        'price_type'
                    );
                }
            }
        );
    }
};