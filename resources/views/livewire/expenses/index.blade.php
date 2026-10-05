<div class="boq-page-stack">
    <x-ui.page-header :title="__('Expenses & Receipts')" icon="fa-receipt" :subtitle="__('Record purchases for your assigned projects and keep receipts together.')">
        <x-slot:actions><x-ui.button type="button" icon="fa-plus" wire:click="create">{{ __('Record expense') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.flash />
    @if($projects->isEmpty())
        <x-ui.card><p class="text-sm text-slate-600">{{ __('No assigned projects are available. Ask your organisation administrator to assign a project.') }}</p></x-ui.card>
    @endif
    @if($totals->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach($totals as $total)
                <x-ui.card :title="__('Recorded expenses')"><p class="text-xl font-bold"><x-money :amount="$total->amount" :currency="$total->currency" /></p></x-ui.card>
            @endforeach
        </div>
    @endif
    <x-ui.card :padded="false">
        <div class="boq-toolbar">
            <x-ui.field :label="__('Search')" for="expense-search" class="boq-toolbar-grow"><input id="expense-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field" maxlength="255"></x-ui.field>
            <x-ui.field :label="__('Project')" for="expense-project-filter"><select id="expense-project-filter" wire:model.live="projectFilter" class="boq-field"><option value="">{{ __('All projects') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></x-ui.field>
        </div>
        <div class="overflow-x-auto"><x-ui.table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Project') }}</th><th>{{ __('Description') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Status') }}</th><th class="is-numeric">{{ __('Total') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>@forelse($expenses as $expense)
                <tr wire:key="expense-{{ $expense->id }}"><td class="whitespace-nowrap">{{ \App\Support\Format::date($expense->purchase_date) }}</td><td>{{ $expense->project->name }}</td><td>{{ $expense->description }}</td><td>{{ $expense->supplier ?: '—' }}</td><td><x-ui.badge :color="$expense->is_planned ? 'success' : 'warning'">{{ $expense->is_planned ? __('Planned') : __('Unplanned') }}</x-ui.badge></td><td class="is-numeric"><x-money :amount="$expense->total" :currency="$expense->currency" /></td><td><button type="button" wire:click="show({{ $expense->id }})" class="boq-btn-ghost">{{ __('Details & receipts') }}</button><button type="button" wire:click="edit({{ $expense->id }})" class="boq-btn-ghost">{{ __('Edit') }}</button></td></tr>
            @empty<tr><td colspan="7"><x-ui.empty-state icon="fa-receipt" :title="__('No expenses recorded.')" :description="__('Record a purchase to start tracking project spending.')" /></td></tr>@endforelse</tbody>
        </x-ui.table></div>
        <div class="p-4">{{ $expenses->links() }}</div>
    </x-ui.card>
    @if($showForm)
        <x-ui.modal :title="$expenseId ? __('Edit expense') : __('Record expense')" close="closeForm" submit="save" size="xl">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('Assigned project')" for="expense-project" error="project_id" required>
                    <select id="expense-project" wire:model.live="project_id" class="boq-field" @disabled($expenseId !== null)><option value="">{{ __('Select a project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
                </x-ui.field>
                <x-ui.field :label="__('Purchase date')" for="expense-date" error="purchase_date" required><input id="expense-date" type="date" wire:model="purchase_date" class="boq-field" required></x-ui.field>
                <x-ui.field :label="__('Supplier')" for="expense-supplier" error="supplier"><input id="expense-supplier" wire:model="supplier" class="boq-field" maxlength="255"></x-ui.field>
                <x-ui.field :label="__('Payment method')" for="expense-payment" error="payment_method"><input id="expense-payment" wire:model="payment_method" class="boq-field" maxlength="100"></x-ui.field>
                <x-ui.field :label="__('Description')" for="expense-description" error="description" required class="sm:col-span-2"><textarea id="expense-description" wire:model="description" class="boq-field" rows="3" maxlength="10000" required></textarea></x-ui.field>
                <x-ui.field :label="__('Quantity')" for="expense-quantity" error="quantity" required><input id="expense-quantity" type="number" wire:model="quantity" class="boq-field" min="0.001" step="0.001" required></x-ui.field>
                <x-ui.field :label="__('Unit')" for="expense-unit" error="unit" required><input id="expense-unit" wire:model="unit" class="boq-field" maxlength="50" required></x-ui.field>
                <x-ui.field :label="__('Rate')" for="expense-rate" error="rate" required><input id="expense-rate" type="number" wire:model="rate" class="boq-field" min="0" step="0.01" required></x-ui.field>
                <x-ui.field :label="__('Currency')" for="expense-currency" error="currency" required><x-currency-select id="expense-currency" wire:model="currency" :current="$currency" /></x-ui.field>
                <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" wire:model.live="is_planned" class="rounded border-slate-300"><span>{{ __('Planned purchase') }}</span></label>
                @if(!$is_planned)<x-ui.field :label="__('Reason for unplanned purchase')" for="expense-explanation" error="explanation" required class="sm:col-span-2"><textarea id="expense-explanation" wire:model="explanation" class="boq-field" rows="3" maxlength="10000" required></textarea></x-ui.field>@endif
                <p class="text-sm text-slate-500 sm:col-span-2">{{ __('The total is calculated from quantity and rate when you save.') }}</p>
            </div>
            <x-slot:footer><x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save expense') }}</x-ui.button></x-slot:footer>
        </x-ui.modal>
    @elseif($selectedExpense)
        <x-ui.modal :title="__('Details & receipts')" close="closeDetails" size="lg">
            <h3 class="font-semibold">{{ $selectedExpense->description }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $selectedExpense->project->name }} · {{ \App\Support\Format::date($selectedExpense->purchase_date) }}</p>
            <p class="my-4 text-xl font-bold"><x-money :amount="$selectedExpense->total" :currency="$selectedExpense->currency" /></p>
            <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="font-semibold">{{ __('Supplier') }}</dt><dd>{{ $selectedExpense->supplier ?: '—' }}</dd></div><div><dt class="font-semibold">{{ __('Payment method') }}</dt><dd>{{ $selectedExpense->payment_method ?: '—' }}</dd></div><div><dt class="font-semibold">{{ __('Quantity / Unit / Rate') }}</dt><dd>{{ $selectedExpense->quantity }} {{ $selectedExpense->unit }} × {{ $selectedExpense->rate }}</dd></div></dl>
            @if(!$selectedExpense->is_planned)<p class="mt-4 whitespace-pre-line text-sm"><strong>{{ __('Reason for unplanned purchase') }}:</strong> {{ $selectedExpense->explanation }}</p>@endif
            <h3 class="mt-6 mb-3 font-semibold">{{ __('Receipts') }}</h3>
            @forelse($selectedExpense->receipts as $receipt)<a class="boq-btn-secondary mb-2" href="{{ route('expense-receipts.download', $receipt) }}"><i class="fas fa-download" aria-hidden="true"></i>{{ $receipt->original_filename }}</a>@empty<p class="mb-4 text-sm text-slate-500">{{ __('No receipts attached.') }}</p>@endforelse
            <form wire:submit="uploadReceipt" class="mt-4 space-y-3">
                <x-ui.field :label="__('Attach receipt')" for="expense-receipt-file" error="receiptFile"><input id="expense-receipt-file" type="file" wire:model="receiptFile" accept="application/pdf,image/jpeg,image/png,image/webp" class="boq-field"><p class="mt-1 text-xs text-slate-500">{{ __('PDF or image, up to 10 MB.') }}</p></x-ui.field>
                <x-ui.button type="submit" icon="fa-upload" wire:loading.attr="disabled" wire:target="receiptFile,uploadReceipt">{{ __('Upload receipt') }}</x-ui.button>
                <span wire:loading wire:target="receiptFile,uploadReceipt" role="status">{{ __('Uploading...') }}</span>
            </form>
        </x-ui.modal>
    @endif
</div>
