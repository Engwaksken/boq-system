@php
    $adminTabs = [
        ['icon'=>'fa-gauge','label'=>'Overview','url'=>route('admin.index'),'active'=>request()->routeIs('admin.index')],
        ['icon'=>'fa-layer-group','label'=>'Plans','url'=>route('admin.plans'),'active'=>request()->routeIs('admin.plans')],
        ['icon'=>'fa-gift','label'=>'Top-ups','url'=>route('admin.topups'),'active'=>request()->routeIs('admin.topups')],
        ['icon'=>'fa-box-open','label'=>'Versions','url'=>route('admin.versions'),'active'=>request()->routeIs('admin.versions')],
        ['icon'=>'fa-book','label'=>'Rate Library','url'=>route('admin.rates'),'active'=>request()->routeIs('admin.rates')],
        ['icon'=>'fa-truck','label'=>'Suppliers','url'=>route('admin.suppliers'),'active'=>request()->routeIs('admin.suppliers')],
        ['icon'=>'fa-file-invoice','label'=>'Quotations','url'=>route('admin.quotations'),'active'=>request()->routeIs('admin.quotations')],
        ['icon'=>'fa-receipt','label'=>'Subscriptions','url'=>route('admin.subscriptions'),'active'=>request()->routeIs('admin.subscriptions')],
        ['icon'=>'fa-robot','label'=>'AI API Settings','url'=>route('admin.ai-providers'),'active'=>request()->routeIs('admin.ai-providers')],
        ['icon'=>'fa-credit-card','label'=>'Payment Gateways','url'=>route('admin.payment-gateways'),'active'=>request()->routeIs('admin.payment-gateways')],
        ['icon'=>'fa-users','label'=>'Users','url'=>route('admin.users'),'active'=>request()->routeIs('admin.users')],
        ['icon'=>'fa-user-shield','label'=>'Roles & Permissions','url'=>route('admin.roles-permissions'),'active'=>request()->routeIs('admin.roles-permissions')],
        ['icon'=>'fa-magnifying-glass-dollar','label'=>'Hardware Scanner','url'=>route('admin.hardware-scanner'),'active'=>request()->routeIs('admin.hardware-scanner')],
        ['icon'=>'fa-gear','label'=>'Settings','url'=>route('admin.settings'),'active'=>request()->routeIs('admin.settings')],
    ];
@endphp

<div class="boq-panel" style="margin-bottom:1.25rem;overflow:hidden">
    <div class="boq-admin-tabs" role="tablist" aria-label="Administration">
        @foreach($adminTabs as $tab)
            <a href="{{ $tab['url'] }}" class="boq-admin-tab {{ $tab['active'] ? 'is-active' : '' }}">
                <i class="fas {{ $tab['icon'] }}"></i>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</div>
