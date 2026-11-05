<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every change to a plan's price or currency, so subscribers (especially on
 * recurring monthly plans) can see what they paid before and what they pay now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2);
            $table->string('old_currency', 3)->nullable();
            $table->string('new_currency', 3);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['plan_id', 'changed_at']);
        });

        // Start each existing plan's history at its current price.
        DB::table('plans')->orderBy('id')->each(function ($plan): void {
            DB::table('plan_price_changes')->insert([
                'plan_id' => $plan->id,
                'old_price' => null,
                'new_price' => $plan->price ?? 0,
                'old_currency' => null,
                'new_currency' => $plan->currency ?: 'UGX',
                'changed_by' => null,
                'changed_at' => $plan->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_price_changes');
    }
};
