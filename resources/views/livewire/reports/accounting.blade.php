<div class="boq-page-stack">
    <x-ui.page-header :title="__('Project Accounting')" icon="fa-chart-pie" :subtitle="__('Track BOQ budget against recorded expenditure and the remaining balance.')" />
    <x-ui.flash />

    <x-ui.card :padded="false">
        <div class="boq-toolbar">
            <x-ui.field :label="__('Search')" for="report-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="report-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search projects...') }}">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="report-status" class="w-full sm:w-40">
                <select id="report-status" wire:model.live="statusFilter" class="boq-field">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="completed">{{ __('Completed') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Period')" for="report-period" class="w-full sm:w-40">
                <select id="report-period" wire:model.live="periodFilter" class="boq-field">
                    <option value="">{{ __('All time') }}</option>
                    <option value="this_month">{{ __('This month') }}</option>
                    <option value="last_month">{{ __('Last month') }}</option>
                    <option value="this_quarter">{{ __('This quarter') }}</option>
                    <option value="this_year">{{ __('This year') }}</option>
                    <option value="last_30_days">{{ __('Last 30 days') }}</option>
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Project')" for="report-project" class="w-full sm:w-56">
                <select id="report-project" wire:model.live="projectFilter" class="boq-field">
                    <option value="">{{ __('All projects') }}</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Rows')" for="report-rows" class="w-full sm:w-24">
                <select id="report-rows" wire:model.live="perPage" class="boq-field">
                    @foreach([10, 15, 25, 50, 100] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if($breakdown)
            <div class="grid gap-3 p-4 sm:grid-cols-3">
                <x-ui.card :title="__('Budget')"><p class="text-xl font-bold"><x-money :amount="$breakdown['budget']" :currency="$breakdown['currency']" /></p></x-ui.card>
                <x-ui.card :title="__('Expenditure')"><p class="text-xl font-bold"><x-money :amount="$breakdown['spent']" :currency="$breakdown['currency']" /></p>@if($breakdown['progress'] !== null)<p class="mt-1 text-xs text-slate-500">{{ __('Used') }}: {{ $breakdown['progress'] }}%</p>@endif</x-ui.card>
                <x-ui.card :title="__('Balance')"><p class="text-xl font-bold"><x-money :amount="$breakdown['balance']" :currency="$breakdown['currency']" /></p></x-ui.card>
            </div>
            <div class="overflow-x-auto"><x-ui.table>
                <thead><tr><th>{{ __('BOQ') }}</th><th>{{ __('Status') }}</th><th class="is-numeric">{{ __('Items') }}</th><th class="is-numeric">{{ __('Budget') }}</th><th class="is-numeric">{{ __('Expenditure') }}</th><th class="is-numeric">{{ __('Balance') }}</th><th class="is-numeric">{{ __('Used') }}</th></tr></thead>
                <tbody>
                    @forelse($breakdown['rows'] as $row)
                        <tr wire:key="report-boq-{{ $row['id'] }}"><td>{{ $row['name'] }}@if($row['code']) <span class="text-slate-400">· {{ $row['code'] }}</span>@endif</td><td>{{ ucfirst(str_replace('_', ' ', (string) $row['status'])) }}</td><td class="is-numeric">{{ $row['items_count'] }}</td><td class="is-numeric"><x-money :amount="$row['budget']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['spent']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['balance']" :currency="$row['currency']" /></td><td class="is-numeric">@if($row['progress'] !== null)<x-ui.badge :color="$row['over_budget'] ? 'danger' : 'success'">{{ $row['progress'] }}%</x-ui.badge>@else—@endif</td></tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state icon="fa-file-invoice-dollar" :title="__('No BOQs for this project.')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table></div>
            <div class="space-y-1 p-4 text-sm text-slate-600">
                <p>{{ __('Unlinked expenditure') }}: <x-money :amount="$breakdown['unlinked_spent']" :currency="$breakdown['currency']" /></p>
                @foreach($breakdown['other_currency'] as $code => $amount)
                    <p>{{ __('Other currency expenditure') }}: <x-money :amount="$amount" :currency="$code" /></p>
                @endforeach
                @if($breakdown['excluded_count'] > 0)
                    <p>{{ __(':count BOQ(s) excluded because they are not approved.', ['count' => $breakdown['excluded_count']]) }}</p>
                @endif
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Portfolio')" :padded="false">
        <div class="overflow-x-auto"><x-ui.table>
            <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Status') }}</th><th class="is-numeric">{{ __('Budget') }}</th><th class="is-numeric">{{ __('Expenditure') }}</th><th class="is-numeric">{{ __('Balance') }}</th><th class="is-numeric">{{ __('Used') }}</th><th class="is-numeric">{{ __('Unlinked') }}</th></tr></thead>
            <tbody>
                @forelse($portfolio as $row)
                    <tr wire:key="report-project-{{ $row['id'] }}"><td><button type="button" wire:click="$set('projectFilter', '{{ $row['id'] }}')" class="boq-link-button">{{ $row['name'] }}</button></td><td>{{ ucfirst(str_replace('_', ' ', (string) $row['status'])) }}</td><td class="is-numeric"><x-money :amount="$row['budget']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['spent']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['balance']" :currency="$row['currency']" /></td><td class="is-numeric">{{ $row['progress'] !== null ? $row['progress'].'%' : '—' }}</td><td class="is-numeric"><x-money :amount="$row['unlinked']" :currency="$row['currency']" /></td></tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="fa-chart-pie" :title="__('No projects found.')" :description="__('Try adjusting the search, status or period filters.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table></div>
        @if($portfolio->hasPages())
            <div class="boq-pagination">{{ $portfolio->links() }}</div>
        @endif
    </x-ui.card>
</div>
