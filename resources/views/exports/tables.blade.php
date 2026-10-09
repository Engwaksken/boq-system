<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
    @page { margin: 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
    h1 { font-size: 18px; } h2 { font-size: 13px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 20px; }
    thead { display: table-header-group; }
    th, td { border: 1px solid #cbd5e1; padding: 5px; word-wrap: break-word; vertical-align: top; }
    th { background: #f1f5f9; text-align: left; }
    .meta { color: #64748b; margin-bottom: 15px; }
</style></head><body>
<h1>{{ __('Filtered data export') }}</h1>
<p class="meta">{{ config('app.name') }} · {{ $generatedAt->format('Y-m-d H:i:s') }} · {{ __('All matching records') }}</p>
@foreach($sections as $section)
    <h2>{{ $section['title'] }} ({{ count($section['rows']) }})</h2>
    <table><thead><tr>@foreach($section['columns'] as $label)<th>{{ __($label) }}</th>@endforeach</tr></thead><tbody>
    @forelse($section['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @empty<tr><td colspan="{{ count($section['columns']) }}">{{ __('No matching records.') }}</td></tr>@endforelse
    </tbody></table>
@endforeach
</body></html>
