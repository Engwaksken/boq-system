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
            @if($expenseId === null)
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="mb-2 text-sm font-semibold">{{ __('Fill from a receipt (optional)') }}</p>
                    <div class="flex items-end gap-3">
                        <x-ui.field :label="__('Receipts')" for="expense-extract-file" error="extractFiles" class="flex-1">
                            <input id="expense-extract-file" type="file" multiple wire:model="extractFiles" accept="application/pdf,image/jpeg,image/png,image/webp" class="boq-field">
                        </x-ui.field>
                        <x-ui.button type="button" icon="fa-wand-magic-sparkles" wire:click="extractFromReceipt" loading="extractFiles,extractFromReceipt,reviewReceipt">{{ __('Extract again') }}</x-ui.button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Choose up to 10 receipts, 10 MB each. Each receipt is extracted and saved separately. Review one receipt, save it, then continue to the next.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Printed and legible handwritten receipts are supported by the configured vision AI. Use a clear photo and verify all extracted items, quantities and prices. Unclear handwriting may require manual entry.') }}</p>
                    <span wire:loading wire:target="extractFiles,reviewReceipt,extractFromReceipt" role="status">{{ __('Uploading or reading receipt...') }}</span>
                    @foreach($errors->get('extractFiles.*') as $messages) @foreach($messages as $message)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@endforeach @endforeach
                    @error('extractFile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($extractFiles as $index => $file)
                            <button type="button" wire:click="reviewReceipt({{ $index }})" wire:loading.attr="disabled" wire:target="extractFiles,reviewReceipt,extractFromReceipt,save" class="{{ $activeReceiptIndex === $index ? 'boq-btn-primary' : 'boq-btn-secondary' }}">{{ $file->getClientOriginalName() }} @if($activeReceiptIndex === $index) · {{ __('Reviewing') }} @endif</button>
                        @endforeach
                    </div>
                    @foreach($extractionWarnings as $warning)<p class="mt-2 text-xs text-amber-600"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ $warning }}</p>@endforeach
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('Assigned project')" for="expense-project" error="project_id" required>
                    <select id="expense-project" wire:model.live="project_id" class="boq-field" @disabled($expenseId !== null)><option value="">{{ __('Select a project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
                </x-ui.field>
                <x-ui.field :label="__('Purchase date')" for="expense-date" error="purchase_date" required><input id="expense-date" type="date" wire:model="purchase_date" class="boq-field" required></x-ui.field>
                <x-ui.field :label="__('Supplier')" for="expense-supplier" error="supplier"><input id="expense-supplier" wire:model="supplier" class="boq-field" maxlength="255"></x-ui.field>
                <x-ui.field :label="__('Payment method')" for="expense-payment" error="payment_method"><input id="expense-payment" wire:model="payment_method" class="boq-field" maxlength="100"></x-ui.field>
                <x-ui.field :label="__('Description')" for="expense-description" error="description" required class="sm:col-span-2"><textarea id="expense-description" wire:model="description" class="boq-field" rows="3" maxlength="10000" required></textarea></x-ui.field>
                <x-ui.field :label="__('Quantity')" for="expense-quantity" error="quantity" required><input id="expense-quantity" type="number" wire:model.live.debounce.300ms="quantity" class="boq-field" min="0.001" step="0.001" required></x-ui.field>
                <x-ui.field :label="__('Unit')" for="expense-unit" error="unit" required><input id="expense-unit" wire:model.live.debounce.300ms="unit" class="boq-field" maxlength="50" required></x-ui.field>
                <x-ui.field :label="__('Rate')" for="expense-rate" error="rate" required><input id="expense-rate" type="number" wire:model.live.debounce.300ms="rate" class="boq-field" min="0" step="0.01" required></x-ui.field>
                <x-ui.field :label="__('Currency')" for="expense-currency" error="currency" required><x-currency-select id="expense-currency" wire:model.live="currency" :current="$currency" /></x-ui.field>
                @if($expenseId === null)
                    @if(auth()->user()->hasPermission('boq.view'))
                    <div class="sm:col-span-2">
                        <x-ui.field :label="__('Compare first item with project BOQ')" for="expense-boq-item" error="boq_item_id">
                            <select id="expense-boq-item" wire:model.live="boq_item_id" class="boq-field"><option value="">{{ __('Not linked / outside BOQ') }}</option>@foreach($boqItems as $boqItem)<option value="{{ $boqItem->id }}">{{ $boqItem->boq->name }} · {{ $boqItem->item_code }} {{ $boqItem->description }} ({{ $boqItem->unit }})</option>@endforeach</select>
                        </x-ui.field>
                        @if(isset($budgetComparisons[0]))<x-expense-budget :comparison="$budgetComparisons[0]" />@endif
                    </div>
                    @endif
                    <div class="sm:col-span-2 rounded-lg border border-slate-200 p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h3 class="font-semibold">{{ __('Additional items') }}</h3>
                            <x-ui.button type="button" variant="secondary" size="sm" icon="fa-plus" wire:click="addExpenseItem">{{ __('Add item') }}</x-ui.button>
                        </div>
                        @foreach($additionalItems as $index => $line)
                            <div class="mb-3 grid gap-3 border-b border-slate-100 pb-3 sm:grid-cols-12" wire:key="expense-line-{{ $index }}">
                                <div class="sm:col-span-5"><label class="boq-field-label">{{ __('Description') }}</label><input wire:model="additionalItems.{{ $index }}.description" class="boq-field" maxlength="10000"></div>
                                <div class="sm:col-span-2"><label class="boq-field-label">{{ __('Quantity') }}</label><input type="number" min="0.001" step="0.001" wire:model.live.debounce.300ms="additionalItems.{{ $index }}.quantity" class="boq-field"></div>
                                <div class="sm:col-span-2"><label class="boq-field-label">{{ __('Unit') }}</label><input wire:model.live.debounce.300ms="additionalItems.{{ $index }}.unit" class="boq-field" maxlength="50"></div>
                                <div class="sm:col-span-2"><label class="boq-field-label">{{ __('Rate') }}</label><input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="additionalItems.{{ $index }}.rate" class="boq-field"></div>
                                <div class="flex items-end sm:col-span-1"><button type="button" class="boq-icon-btn boq-icon-danger" wire:click="removeExpenseItem({{ $index }})" aria-label="{{ __('Remove item') }}"><i class="fas fa-trash" aria-hidden="true"></i></button></div>
                                @if(auth()->user()->hasPermission('boq.view'))
                                <div class="sm:col-span-12"><label class="boq-field-label">{{ __('Project BOQ item') }}</label><select wire:model.live="additionalItems.{{ $index }}.boq_item_id" class="boq-field"><option value="">{{ __('Not linked / outside BOQ') }}</option>@foreach($boqItems as $boqItem)<option value="{{ $boqItem->id }}">{{ $boqItem->boq->name }} · {{ $boqItem->item_code }} {{ $boqItem->description }} ({{ $boqItem->unit }})</option>@endforeach</select>
                                    @if(isset($budgetComparisons[$index + 1]))<x-expense-budget :comparison="$budgetComparisons[$index + 1]" />@endif
                                </div>
                                @endif
                            </div>
                        @endforeach
                        <p class="text-xs text-slate-500">{{ __('Each item is saved under this expense record; the total is calculated from all items.') }}</p>
                    </div>
                    @foreach($errors->get('items.*') as $messages) @foreach($messages as $message)<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@endforeach @endforeach
                @endif
                <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" wire:model.live="is_planned" class="rounded border-slate-300"><span>{{ __('Planned purchase') }}</span></label>
                @if(!$is_planned)<x-ui.field :label="__('Reason for unplanned purchase')" for="expense-explanation" error="explanation" required class="sm:col-span-2"><textarea id="expense-explanation" wire:model="explanation" class="boq-field" rows="3" maxlength="10000" required></textarea></x-ui.field>@endif
                <p class="text-sm text-slate-500 sm:col-span-2">{{ __('The total is calculated from quantity and rate when you save.') }}</p>
            </div>
            <x-slot:footer><x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save,extractFiles,reviewReceipt,extractFromReceipt">{{ count($extractFiles) > 1 ? __('Save & review next receipt') : __('Save expense') }}</x-ui.button></x-slot:footer>
        </x-ui.modal>
    @elseif($selectedExpense)
        <x-ui.modal :title="__('Details & receipts')" close="closeDetails" size="lg">
            <h3 class="font-semibold">{{ $selectedExpense->description }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $selectedExpense->project->name }} · {{ \App\Support\Format::date($selectedExpense->purchase_date) }}</p>
            <p class="my-4 text-xl font-bold"><x-money :amount="$selectedExpense->total" :currency="$selectedExpense->currency" /></p>
            <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="font-semibold">{{ __('Supplier') }}</dt><dd>{{ $selectedExpense->supplier ?: '—' }}</dd></div><div><dt class="font-semibold">{{ __('Payment method') }}</dt><dd>{{ $selectedExpense->payment_method ?: '—' }}</dd></div></dl>
            @if($selectedExpense->items->isNotEmpty())
                <h3 class="mt-4 mb-2 font-semibold">{{ __('Expense items') }}</h3>
                <div class="overflow-x-auto"><x-ui.table><thead><tr><th>{{ __('Description') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit') }}</th><th class="is-numeric">{{ __('Rate') }}</th><th class="is-numeric">{{ __('Total') }}</th></tr></thead><tbody>
                    @foreach($selectedExpense->items as $line)<tr wire:key="expense-detail-line-{{ $line->id }}"><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit }}</td><td class="is-numeric">{{ $line->rate }}</td><td class="is-numeric">{{ $line->total }}</td></tr>@endforeach
                </tbody></x-ui.table></div>
            @else
                <div class="mt-4 text-sm"><strong>{{ __('Quantity / Unit / Rate') }}:</strong> {{ $selectedExpense->quantity }} {{ $selectedExpense->unit }} × {{ $selectedExpense->rate }}</div>
            @endif
            @if(!$selectedExpense->is_planned)<p class="mt-4 whitespace-pre-line text-sm"><strong>{{ __('Reason for unplanned purchase') }}:</strong> {{ $selectedExpense->explanation }}</p>@endif
            @if($savedBudgetComparisons !== [])<h3 class="mt-4 font-semibold">{{ __('BOQ budget progress') }}</h3>@endif
            @foreach($savedBudgetComparisons as $comparison)<x-expense-budget :comparison="$comparison" />@endforeach
            <h3 class="mt-6 mb-3 font-semibold">{{ __('Receipts') }}</h3>
            @forelse($selectedExpense->receipts as $receipt)<a class="boq-btn-secondary mb-2" href="{{ route('expense-receipts.download', $receipt) }}"><i class="fas fa-download" aria-hidden="true"></i>{{ $receipt->original_filename }}</a>@empty<p class="mb-4 text-sm text-slate-500">{{ __('No receipts attached.') }}</p>@endforelse
            <form wire:submit="uploadReceipt" class="mt-4 space-y-3">
                <x-ui.field :label="__('Attach receipts')" for="expense-receipt-file" error="receiptFiles"><input id="expense-receipt-file" type="file" multiple wire:model="receiptFiles" accept="application/pdf,image/jpeg,image/png,image/webp" class="boq-field"><p class="mt-1 text-xs text-slate-500">{{ __('Up to 10 PDFs or images, 10 MB each. Attachments here do not change expense items.') }}</p></x-ui.field>
                @foreach($errors->get('receiptFiles.*') as $messages) @foreach($messages as $message)<p class="text-sm text-red-600">{{ $message }}</p>@endforeach @endforeach
                <x-ui.button type="submit" icon="fa-upload" wire:loading.attr="disabled" wire:target="receiptFiles,uploadReceipt">{{ __('Upload receipts') }}</x-ui.button>
                <span wire:loading wire:target="receiptFiles,uploadReceipt" role="status">{{ __('Uploading...') }}</span>
            </form>
        </x-ui.modal>
    @endif
</div>
