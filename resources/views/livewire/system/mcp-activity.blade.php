<div>
    <div class="mb-6">
        <p class="text-sm font-semibold text-indigo-600">System / AI & MCP</p>
        <h1 class="text-2xl font-bold text-gray-900">MCP Activity</h1>
        <p class="mt-1 text-sm text-gray-500">Read-only audit trail for BOQ AI service requests.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search MCP tool..." class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All statuses</option>
            <option value="success">Success</option>
            <option value="error">Error</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">User</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Model</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tool</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Time</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-3">{{ $log->created_at->format('Y-m-d H:i:s') }}</td><td class="px-4 py-3">{{ $log->user?->name ?? 'Service account' }}</td><td class="px-4 py-3">{{ data_get($log->new_value, 'model', 'unknown') }}</td><td class="px-4 py-3 font-medium">{{ str_replace('mcp.', '', $log->action) }}</td><td class="px-4 py-3">{{ $log->entity_id ?? '-' }}</td><td class="px-4 py-3">{{ data_get($log->new_value, 'status') }}</td><td class="px-4 py-3 text-right">{{ data_get($log->new_value, 'execution_duration_ms', 0) }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No MCP activity recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($logs->hasPages())<div class="border-t border-gray-200 px-4 py-3">{{ $logs->links() }}</div>@endif
    </div>
</div>
