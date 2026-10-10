@props(['project', 'showLabel' => true])
@php
    $value = $project->progressPercent();
@endphp
<div class="flex w-full items-center gap-2">
    <div
        class="h-2 w-full min-w-14 overflow-hidden rounded-full bg-slate-200"
        role="progressbar"
        aria-valuenow="{{ $value }}"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $value }}%"></div>
    </div>
    @if($showLabel)
        <span class="whitespace-nowrap text-xs font-semibold text-slate-600">{{ $value }}%</span>
    @endif
</div>
