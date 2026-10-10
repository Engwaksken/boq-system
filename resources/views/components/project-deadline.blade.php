@props(['project'])
@php
    $remaining = $project->remainingDays();
@endphp
@if($remaining === null)
    <span class="boq-table-empty">—</span>
@elseif($remaining < 0)
    <span class="inline-flex items-center gap-1 text-xs font-semibold text-red-600">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        {{ trans_choice(':count day overdue|:count days overdue', abs($remaining), ['count' => abs($remaining)]) }}
    </span>
@elseif($remaining === 0)
    <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        {{ __('Due today') }}
    </span>
@else
    <span @class(['inline-flex items-center gap-1 text-xs font-semibold', $remaining <= 14 ? 'text-amber-600' : 'text-slate-600'])>
        <i class="fas fa-clock" aria-hidden="true"></i>
        {{ trans_choice(':count day left|:count days left', $remaining, ['count' => $remaining]) }}
    </span>
@endif
