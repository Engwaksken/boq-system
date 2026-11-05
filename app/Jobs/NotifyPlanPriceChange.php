<?php

namespace App\Jobs;

use App\Models\PlanPriceChange;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\ActivityNotice;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tell everyone currently subscribed to a plan that its price changed.
 * Billing changes are always delivered in-app (like renewal reminders), and
 * by email to verified addresses. Idempotent per user and change, so a retry
 * never notifies anyone twice.
 */
class NotifyPlanPriceChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $planPriceChangeId) {}

    public function handle(): void
    {
        $change = PlanPriceChange::query()->with('plan')->find($this->planPriceChangeId);
        if (! $change || ! $change->plan) {
            return;
        }

        $title = __('Price change: :plan', ['plan' => $change->plan->name]);
        $message = self::message($change);
        $key = 'plan-price-change:'.$change->id;
        $url = route('subscriptions.index', ['tab' => 'price-history']);

        $userIds = Subscription::query()
            ->where('plan_id', $change->plan_id)
            ->whereIn('status', ['active', 'trial', 'grace_period'])
            ->get(['user_id', 'beneficiary_id', 'payer_id'])
            ->flatMap(fn (Subscription $s) => [$s->user_id, $s->beneficiary_id, $s->payer_id])
            ->filter()
            ->unique()
            ->values();

        User::query()->whereIn('id', $userIds)->each(function (User $user) use ($change, $title, $message, $key, $url): void {
            $created = DB::transaction(function () use ($user, $change, $title, $message, $key): bool {
                User::query()->whereKey($user->id)->lockForUpdate()->first();
                if (UserNotification::where('user_id', $user->id)->where('data->alert_key', $key)->exists()) {
                    return false;
                }

                UserNotification::create([
                    'user_id' => $user->id,
                    'type' => 'subscription',
                    'title' => $title,
                    'message' => $message,
                    'data' => ['alert_key' => $key, 'plan_id' => $change->plan_id, 'plan_price_change_id' => $change->id],
                ]);

                return true;
            });

            if ($created && $user->email_verified_at !== null) {
                try {
                    $user->notify(new ActivityNotice($title, $message, $url));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    public static function message(PlanPriceChange $change): string
    {
        $new = Format::money($change->new_price, $change->new_currency);

        if ($change->old_price === null) {
            return __('The :plan plan is priced at :new.', ['plan' => $change->plan->name, 'new' => $new]);
        }

        return __('The :plan plan price changed from :old to :new. Your next renewal uses the new price.', [
            'plan' => $change->plan->name,
            'old' => Format::money($change->old_price, $change->old_currency ?: $change->new_currency),
            'new' => $new,
        ]);
    }
}
