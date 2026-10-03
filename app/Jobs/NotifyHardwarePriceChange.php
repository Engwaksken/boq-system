<?php

namespace App\Jobs;

use App\Models\HardwareBookmark;
use App\Models\PriceHistory;
use App\Models\UserNotification;
use App\Notifications\HardwarePriceChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Deliver alerts for a persisted, verified history row only. */
class NotifyHardwarePriceChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 300, 600, 1200];

    public function __construct(public readonly int $priceHistoryId) {}

    public function handle(): void
    {
        $history = PriceHistory::query()->with('hardwarePrice')->find($this->priceHistoryId);
        if (! $history || ! $history->hardwarePrice || ! $history->hardwarePrice->last_verified_at
            || ! $history->source_url || ! $history->recorded_at) {
            return;
        }

        $price = $history->hardwarePrice;
        HardwareBookmark::query()->where('hardware_price_id', $price->id)->with('user')->each(function ($bookmark) use ($history, $price): void {
            $user = $bookmark->user;
            if (! $user || ! $user->email_verified_at) {
                return;
            }

            $preferences = $user->notificationPreferences();
            $emailEnabled = ($preferences['email_price_alerts'] ?? false) === true;
            $inAppEnabled = ($preferences['in_app_price_alerts'] ?? false) === true;
            if (! $emailEnabled && ! $inAppEnabled) {
                return;
            }

            $key = 'hardware-price-history:'.$history->id;
            $notification = DB::transaction(function () use ($user, $key, $history, $price, $inAppEnabled, $emailEnabled) {
                $row = UserNotification::query()->where('user_id', $user->id)->where('data->alert_key', $key)->lockForUpdate()->first();
                if (! $row) {
                    $row = UserNotification::create([
                        'user_id' => $user->id,
                        'type' => 'price_alert',
                        'title' => 'Hardware price changed',
                        'message' => sprintf('%s price is now %s %s.', $price->item_name, $history->currency, $history->price),
                        'data' => ['alert_key' => $key, 'hardware_price_id' => $price->id, 'price_history_id' => $history->id,
                            'in_app_enabled' => $inAppEnabled, 'email_enabled' => $emailEnabled, 'email_sent_at' => null],
                    ]);
                }

                return $row;
            });

            $data = $notification->data ?? [];
            if (($data['email_enabled'] ?? false) && empty($data['email_sent_at'])) {
                $user->notify(new HardwarePriceChanged(
                    $price->item_name,
                    $history->supplier ?: ($price->supplier ?? 'Unknown supplier'),
                    $notification->message,
                ));
                $data['email_sent_at'] = now()->toIso8601String();
                $notification->forceFill(['data' => $data])->save();
            }
        });
    }
}
