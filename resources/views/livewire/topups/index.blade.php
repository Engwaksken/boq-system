<div class="boq-subscriptions-page">

    {{-- HEADER --}}
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-gift"></i>
                Top-ups &amp; Add-ons
            </h1>
            <p class="boq-page-subtitle">
                Buy feature updates, usage credits and one-off unlocks for your workspace.
            </p>
        </div>
        @if($currentSubscription)
            <div class="boq-current-subscription">
                <div class="boq-current-subscription-label">Current Plan</div>
                <div class="boq-current-subscription-name">{{ $currentSubscription->plan?->name ?? '—' }}</div>
            </div>
        @endif
    </div>

    {{-- FLASH --}}
    @if(session('message'))
        <div class="boq-flash">
            <i class="fas fa-circle-check"></i>
            {{ session('message') }}
        </div>
    @endif

    @if($catalog->isEmpty())
        <div class="boq-panel" style="padding:3rem;text-align:center;color:#64748b">
            <i class="fas fa-box-open" style="font-size:2rem;opacity:.6"></i>
            <p style="margin-top:.75rem">No top-ups are available right now.</p>
        </div>
    @else
        <div class="boq-stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            @foreach($catalog as $item)
                <div class="boq-stat-card boq-stat-green" style="flex-direction:column;align-items:flex-start;gap:.5rem">
                    <div style="width:100%;display:flex;justify-content:space-between;align-items:flex-start">
                        <div>
                            <p class="boq-stat-label" style="text-transform:uppercase;letter-spacing:.04em">
                                {{ str_replace('_', ' ', $item['type']) }}
                            </p>
                            <p class="boq-stat-value" style="font-size:1.05rem">{{ $item['name'] }}</p>
                        </div>
                        @if($item['owned'])
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Owned</span>
                        @endif
                    </div>

                    @if($item['description'])
                        <p style="font-size:.85rem;color:#475569">{{ $item['description'] }}</p>
                    @endif

                    <div style="font-size:.82rem;color:#475569;display:flex;flex-wrap:wrap;gap:.35rem">
                        @if($item['release_version'])
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">v{{ $item['release_version'] }}</span>
                        @endif
                        @foreach(($item['usage_credits'] ?? []) as $key=>$value)
                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs">{{ str_replace('_', ' ', $key) }}: {{ $value }}</span>
                        @endforeach
                        @foreach(array_slice($item['included_features'], 0, 3) as $feature)
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $feature }}</span>
                        @endforeach
                    </div>

                    <div style="width:100%;display:flex;justify-content:space-between;align-items:center;margin-top:.25rem">
                        <strong>{{ $item['currency'] }} {{ number_format((float) $item['price'], 0) }}</strong>
                        <span style="font-size:.78rem;color:#64748b">
                            {{ $item['is_permanent'] || ! $item['duration_days'] ? 'One-time' : $item['duration_days'].' days' }}
                        </span>
                    </div>
                    @unless($planCode)
                        <p style="font-size:.72rem;color:#b45309">Subscribe to a plan before buying top-ups.</p>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif

    @if($purchases->isNotEmpty())
        <h2 class="boq-page-title" style="margin-top:2rem">
            <i class="fas fa-history"></i>
            Purchase History
        </h2>
        <div class="boq-panel">
            <table class="boq-table min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        @foreach(['Top-up','Status','Purchased','Expires','Reference'] as $h)
                            <th class="px-4 py-3 text-left">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $purchase->topup?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs {{ $purchase->isValid() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $purchase->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $purchase->purchased_at?->toDateString() ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $purchase->expires_at?->toDateString() ?? ($purchase->is_permanent ? 'Permanent' : '—') }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $purchase->transaction?->reference ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>