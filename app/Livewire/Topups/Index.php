<?php

namespace App\Livewire\Topups;

use App\Models\Subscription;
use App\Models\Topup;
use App\Models\TopupPurchase;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TopupService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?Subscription $currentSubscription = null;

    public function mount(SubscriptionService $subscriptions): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->currentSubscription = $subscriptions->currentSubscription($user, $user->organisation_id);
    }

    public function render(TopupService $topups)
    {
        /** @var User $user */
        $user = auth()->user();
        $planCode = $this->currentSubscription?->plan?->code;

        $catalog = Topup::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function (Topup $topup) use ($user, $topups, $planCode) {
                return [
                    'id' => $topup->id,
                    'name' => $topup->name,
                    'code' => $topup->code,
                    'description' => $topup->description,
                    'type' => $topup->type,
                    'price' => $topup->price,
                    'currency' => $topup->currency,
                    'duration_days' => $topup->duration_days,
                    'is_permanent' => $topup->is_permanent,
                    'release_version' => $topup->release_version,
                    'included_features' => $topup->included_features ?? [],
                    'usage_credits' => $topup->usage_credits ?? [],
                    'owned' => $topups->purchasedCount($topup, $user) > 0,
                    'purchasable' => $topups->purchasableBy($topup, $user, $this->currentSubscription?->plan),
                ];
            });

        $purchases = TopupPurchase::query()
            ->where('user_id', $user->id)
            ->with('topup')
            ->latest()
            ->limit(25)
            ->get();

        return view('livewire.topups.index', [
            'catalog' => $catalog,
            'purchases' => $purchases,
            'planCode' => $planCode,
        ]);
    }
}