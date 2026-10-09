<div class="boq-page-stack">
    <x-ui.page-header :title="__('Project Accounting')" icon="fa-chart-pie" :subtitle="__('Track BOQ budget against recorded expenditure and the remaining balance.')" />
    <x-ui.flash />

    <x-ui.card :padded="false">
        <div class="boq-toolbar">
            <x-ui.field :label="__('Project')" for="report-project" class="boq-toolbar-grow">
                <select id="report-project" wire:model.live="projectFilter" class="boq-field">
                    <option value="">{{ __('All projects') }}</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach
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
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Portfolio')" :padded="false">
        <div class="overflow-x-auto"><x-ui.table>
            <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Status') }}</th><th class="is-numeric">{{ __('Budget') }}</th><th class="is-numeric">{{ __('Expenditure') }}</th><th class="is-numeric">{{ __('Balance') }}</th><th class="is-numeric">{{ __('Used') }}</th></tr></thead>
            <tbody>
                @forelse($portfolio as $row)
                    <tr wire:key="report-project-{{ $row['id'] }}"><td><button type="button" wire:click="$set('projectFilter', '{{ $row['id'] }}')" class="boq-link-button">{{ $row['name'] }}</button></td><td>{{ ucfirst(str_replace('_', ' ', (string) $row['status'])) }}</td><td class="is-numeric"><x-money :amount="$row['budget']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['spent']" :currency="$row['currency']" /></td><td class="is-numeric"><x-money :amount="$row['balance']" :currency="$row['currency']" /></td><td class="is-numeric">{{ $row['progress'] !== null ? $row['progress'].'%' : '—' }}</td></tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="fa-chart-pie" :title="__('No projects assigned.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table></div>
    </x-ui.card>
</div>
