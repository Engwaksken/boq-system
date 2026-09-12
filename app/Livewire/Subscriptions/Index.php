<?php

namespace App\Livewire\Subscriptions;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public $subscriptions;

    public $currentSubscription = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->subscriptions = Subscription::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('organisation_id', $user->organisation_id);
            })
            ->with('plan')
            ->latest()
            ->get();

        $this->currentSubscription = app(SubscriptionService::class)
            ->currentSubscription($user, $user->organisation_id);
    }

    public function render()
    {
        return view('livewire.subscriptions.index');
    }
}