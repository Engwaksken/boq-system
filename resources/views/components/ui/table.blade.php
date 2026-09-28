{{--
    Horizontally scrollable table wrapper. Put <thead>/<tbody> in the slot.
    Use :compact="true" for small tables that should not force a minimum width.
--}}
@props(['compact' => false, 'wrapperClass' => ''])

<div class="boq-table-wrapper {{ $wrapperClass }}">
    <table {{ $attributes->class(['boq-table', 'boq-table-compact' => $compact]) }}>
        {{ $slot }}
    </table>
</div>
