<div class="boq-page-stack">
    <x-ui.page-header
        :eyebrow="__('System / AI & MCP')"
        :title="__('MCP Activity')"
        icon="fa-wave-square"
        :subtitle="__('Read-only audit trail for BOQ AI service requests.')"
    />

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search')" for="mcp-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="mcp-search" wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search MCP tool...') }}" class="boq-field boq-field-with-icon">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="mcp-status" class="w-full sm:w-44">
                <select id="mcp-status" wire:model.live="status" class="boq-field">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="success">{{ __('Success') }}</option>
                    <option value="error">{{ __('Error') }}</option>
                </select>
            </x-ui.field>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Model') }}</th>
                    <th>{{ __('Tool') }}</th>
                    <th>{{ __('Project') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Time') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr wire:key="mcp-log-{{ $log->id }}">
                        <td class="whitespace-nowrap"><x-date :value="$log->created_at" time /></td>
                        <td>{{ $log->user?->name ?? __('Service account') }}</td>
                        <td>{{ data_get($log->new_value, 'model', __('unknown')) }}</td>
                        <td><span class="boq-code">{{ str_replace('mcp.', '', (string) $log->action) }}</span></td>
                        <td>{{ $log->entity_id ?? '—' }}</td>
                        <td>
                            @if(data_get($log->new_value, 'status'))
                                <x-ui.status :status="data_get($log->new_value, 'status')" />
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>
                        <td class="is-numeric">{{ \App\Support\Format::number((float) data_get($log->new_value, 'execution_duration_ms', 0), 0) }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0"><x-ui.empty-state icon="fa-wave-square" :title="__('No MCP activity recorded.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($logs->hasPages())
            <div class="boq-pagination">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
