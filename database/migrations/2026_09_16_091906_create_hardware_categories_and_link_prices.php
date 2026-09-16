<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hardware_categories')) {
            Schema::create('hardware_categories', function (Blueprint $table) {
                $table->id();

                $table->foreignId('organisation_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                $table->string('name', 120);
                $table->string('slug', 140);
                $table->string('description', 500)->nullable();

                $table->string('icon', 80)
                    ->default('fas fa-boxes-stacked');

                $table->boolean('is_active')->default(true);

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    ['organisation_id', 'slug'],
                    'hardware_categories_org_slug_unique'
                );

                $table->index([
                    'organisation_id',
                    'is_active',
                ]);
            });
        }

        if (
            Schema::hasTable('hardware_prices')
            && ! Schema::hasColumn(
                'hardware_prices',
                'hardware_category_id'
            )
        ) {
            Schema::table('hardware_prices', function (Blueprint $table) {
                $table->foreignId('hardware_category_id')
                    ->nullable()
                    ->after('brand')
                    ->constrained('hardware_categories')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hardware_prices')) {
            return;
        }

        /*
         * Convert existing category strings into proper
         * category records without deleting the legacy value.
         */
        $categories = DB::table('hardware_prices')
            ->select(
                'organisation_id',
                'category'
            )
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->get();

        foreach ($categories as $existing) {
            $name = trim((string) $existing->category);

            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name) ?: 'category';

            $category = DB::table('hardware_categories')
                ->where(
                    'organisation_id',
                    $existing->organisation_id
                )
                ->where('slug', $slug)
                ->first();

            if (! $category) {
                $categoryId = DB::table(
                    'hardware_categories'
                )->insertGetId([
                    'organisation_id' => $existing->organisation_id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => null,
                    'icon' => 'fas fa-boxes-stacked',
                    'is_active' => true,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $categoryId = $category->id;
            }

            DB::table('hardware_prices')
                ->where(
                    'organisation_id',
                    $existing->organisation_id
                )
                ->where('category', $name)
                ->whereNull('hardware_category_id')
                ->update([
                    'hardware_category_id' => $categoryId,
                ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('hardware_prices')
            && Schema::hasColumn(
                'hardware_prices',
                'hardware_category_id'
            )
        ) {
            Schema::table('hardware_prices', function (Blueprint $table) {
                $table->dropConstrainedForeignId(
                    'hardware_category_id'
                );
            });
        }

        Schema::dropIfExists('hardware_categories');
    }
};