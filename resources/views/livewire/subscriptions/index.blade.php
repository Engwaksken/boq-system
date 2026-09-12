<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Subscription</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your BOQ System subscription</p>
    </div>

    {{-- Current Subscription --}}
    @if(isset($subscription) && $subscription)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Current Subscription</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : ($subscription->status === 'trial' ? 'bg-blue-100 text-blue-800' : ($subscription->status === 'grace_period' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800')) }}">
                    {{ $subscription->status }}
                </span>
            </div>
            <div class="p-6">
                <p class="text-xl font-semibold text-gray-900">{{ $subscription->plan?->name ?? '?' }}</p>
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $subscription->start_date?->format('M d, Y') ?? '?' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">End Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $subscription->end_date?->format('M d, Y') ?? '?' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Renewal Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $subscription->renewal_date?->format('M d, Y') ?? '?' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Auto Renewal</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $subscription->auto_renewal ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <button type="button" wire:click="cancel" wire:confirm="Are you sure you want to cancel your subscription?"
                            class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-lg transition">Cancel Subscription</button>
                </div>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
            <div class="text-center py-12">
                <p class="text-gray-500">You don't have an active subscription.</p>
                <a href="{{ url('/plans') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                    Browse Plans
                </a>
            </div>
        </div>
    @endif

    {{-- Available Plans --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Available Plans</h2>
        </div>

        @if(isset($plans) && $plans->isNotEmpty())
            <div class="divide-y divide-gray-200">
                @foreach($plans as $plan)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $plan->name }}</p>
                            <p class="text-sm text-gray-500">{{ number_format((float) $plan->price, 2) }} {{ $plan->currency }} / {{ $plan->duration_days ? $plan->duration_days . ' days' : $plan->type }}</p>
                        </div>
                        @if(isset($subscription) && $subscription && $subscription->plan_id === $plan->id)
                            <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">Current Plan</span>
                        @else
                            <button type="button" wire:click="subscribe({{ $plan->id }})"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">Subscribe</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No plans available yet.</p>
            </div>
        @endif
    </div>
</div>
