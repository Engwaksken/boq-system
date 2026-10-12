<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * notification_deliveries: one row per (user, dedupe key) so scheduled
     * reminders are sent once, whatever channels the user has enabled.
     *
     * notification_digest_items: emails held back for a user who chose a digest
     * (hourly, daily or weekly) until the matching digest command sends them.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key', 191);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
        });

        Schema::create('notification_digest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->string('category', 32);
            $table->string('title', 255);
            $table->text('message');
            $table->string('url', 2048)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['frequency', 'sent_at']);
        });

        // Deadline reminders used to be de-duplicated by a reminder_key in the
        // in-app notification's data. Carry those over so they are not re-sent.
        DB::table('user_notifications')
            ->where('type', 'project')
            ->where('data', 'like', '%reminder_key%')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                $deliveries = $rows
                    ->map(fn ($row) => [
                        'user_id' => $row->user_id,
                        'dedupe_key' => json_decode((string) $row->data, true)['reminder_key'] ?? null,
                        'sent_at' => $row->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->filter(fn ($delivery) => is_string($delivery['dedupe_key']))
                    ->all();

                DB::table('notification_deliveries')->insertOrIgnore($deliveries);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_items');
        Schema::dropIfExists('notification_deliveries');
    }
};
