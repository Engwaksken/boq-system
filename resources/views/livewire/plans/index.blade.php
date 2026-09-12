<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Plans & Pricing</h1>
        <p class="mt-1 text-sm text-gray-500">Choose the plan that fits your BOQ workflow</p>
    </div>

    @if(isset($plans) && $plans->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($plans as $plan)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col {{ isset($currentPlanCode) && $currentPlanCode === $plan->code ? 'ring-2 ring-indigo-500' : '' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h2>
                        @if(isset($currentPlanCode) && $currentPlanCode === $plan->code)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Current Plan</span>
                        @endif
                    </div>

                    <div class="mt-4">
                        <span class="text-3xl font-bold text-gray-900">{{ number_format((float) $plan->price, 2) }} {{ $plan->currency }}</span>
                        <span class="text-sm text-gray-500"> / {{ $plan->duration_days ? $plan->duration_days . ' days' : $plan->type }}</span>
                    </div>

                    @if($plan->description)
                        <p class="mt-3 text-sm text-gray-500">{{ $plan->description }}</p>
                    @endif

                    @if($plan->included_features)
                        <ul class="mt-4 space-y-2 flex-1">
                            @foreach($plan->included_features as $feature)
                                <li class="flex items-start gap-2 text-sm text-gray-600">
                                    <svg class="w-4 h-4 mt-0.5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center border-t border-gray-100 pt-4">
                        <div>
                            <p class="text-xs text-gray-500">Projects</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $plan->max_projects ?? 'Unlimited' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">BOQs</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $plan->max_boqs ?? 'Unlimited' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Users</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $plan->max_users ?? 'Unlimited' }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        @if(isset($currentPlanCode) && $currentPlanCode === $plan->code)
                            <span class="inline-flex items-center justify-center w-full px-4 py-2 bg-gray-100 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">Current Plan</span>
                        @else
                            <a href="{{ url('/subscriptions') }}" class="inline-flex items-center justify-center w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                                Choose {{ $plan->name }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="text-center py-12">
                <p class="text-gray-500">No plans available yet.</p>
            </div>
        </div>
    @endif
</div>
