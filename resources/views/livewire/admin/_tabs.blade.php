
@php
    $adminTabs = [
        ['label'=>'Overview','url'=>route('admin.index'),'active'=>request()->routeIs('admin.index')],
        ['label'=>'Plans','url'=>route('admin.plans'),'active'=>request()->routeIs('admin.plans')],
        ['label'=>'Subscriptions','url'=>route('admin.subscriptions'),'active'=>request()->routeIs('admin.subscriptions')],
        ['label'=>'Payment Gateways','url'=>route('admin.payment-gateways'),'active'=>request()->routeIs('admin.payment-gateways')],
        ['label'=>'Users','url'=>route('admin.users'),'active'=>request()->routeIs('admin.users')],
        ['label'=>'Roles & Permissions','url'=>route('admin.roles-permissions'),'active'=>request()->routeIs('admin.roles-permissions')],
        ['label'=>'Hardware Scanner','url'=>route('admin.hardware-scanner'),'active'=>request()->routeIs('admin.hardware-scanner')],
        ['label'=>'Settings','url'=>route('admin.settings'),'active'=>request()->routeIs('admin.settings')],
    ];
@endphp
<div class="boq-panel mb-5 overflow-hidden">
    <div class="boq-admin-tabs" role="tablist" aria-label="Administration">
        @foreach($adminTabs as $tab)
            <a href="{{ $tab['url'] }}" class="boq-admin-tab {{ $tab['active'] ? 'is-active' : '' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</div>
