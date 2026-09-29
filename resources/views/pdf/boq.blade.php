<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $boq->reference }} – {{ $boq->name }}</title>
    <style>
        /* The top margin leaves room for the fixed header on every page. */
        @page { margin: 158px 36px 64px 36px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1e293b; }

        /* Repeated on every page (dompdf repeats fixed elements). */
        .watermark { position: fixed; top: 32%; left: 18%; width: 64%; text-align: center; opacity: .06; z-index: -1; }
        .watermark img { width: 100%; }
        .watermark .name { font-size: 54px; font-weight: bold; color: #05645b; transform: rotate(-30deg); }

        /* Header: logo | company details | document details, with the rule below it. */
        header { position: fixed; top: -134px; left: 0; right: 0; height: 112px; border-bottom: 2px solid #05645b; }
        header table { width: 100%; border-collapse: collapse; }
        header td { vertical-align: top; padding: 0; }
        header .logo { width: 78px; padding-right: 12px; }
        header .logo img { max-width: 76px; max-height: 76px; }
        header .company { font-size: 15px; font-weight: bold; color: #0f172a; margin: 0 0 5px; line-height: 1.2; }
        header .contact { font-size: 8.5px; color: #475569; line-height: 1.55; }
        header .contact span.sep { color: #94a3b8; padding: 0 3px; }
        header .doc { width: 34%; text-align: right; font-size: 9px; color: #475569; line-height: 1.6; padding-left: 12px; }
        header .doc strong { display: block; font-size: 14px; letter-spacing: .5px; color: #05645b; margin-bottom: 4px; }

        footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 30px; border-top: 1px solid #cbd5e1; font-size: 8px; color: #64748b; }
        footer .page:after { content: "Page " counter(page) " of " counter(pages); }

        h1 { font-size: 16px; margin: 0 0 8px; color: #0f172a; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 3px 6px; vertical-align: top; font-size: 9.5px; }
        .meta .label { color: #64748b; width: 18%; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #05645b; color: #fff; padding: 5px; font-size: 9px; text-align: left; }
        table.items td { padding: 4px 5px; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
        table.items tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .unpriced { color: #b45309; }

        table.totals { width: 42%; margin-left: 58%; margin-top: 10px; border-collapse: collapse; }
        table.totals td { padding: 4px 6px; font-size: 10px; }
        table.totals .grand td { border-top: 2px solid #05645b; font-size: 12px; font-weight: bold; color: #05645b; }

        .notes { margin-top: 16px; font-size: 9px; color: #475569; }
        .signoff { margin-top: 26px; width: 100%; }
        .signoff td { width: 50%; font-size: 9px; padding-top: 22px; border-top: 1px solid #94a3b8; }
    </style>
</head>
<body>
    @php
        $companyName = $company['company_name'] ?? ($preparedBy ?: config('app.name'));
        $money = fn ($value) => \App\Support\Format::money($value, $currency);
        // Company details grouped into at most four short lines.
        $contact = collect([
            [collect([$company['physical_address'] ?? null, $company['city'] ?? null, $company['country_name'] ?? null])->filter()->implode(', ')],
            [
                $company['postal_address'] ?? null,
                collect([$company['telephone'] ?? null, $company['alt_telephone'] ?? null])->filter()->implode(' / ') ?: null,
            ],
            [$company['email'] ?? null, $company['website'] ?? null],
            [
                ! empty($company['registration_number']) ? __('Reg. No.').' '.$company['registration_number'] : null,
                ! empty($company['tin']) ? __('TIN').' '.$company['tin'] : null,
            ],
        ])
            ->map(fn (array $parts) => collect($parts)->map(fn ($part) => trim((string) $part))->filter()->map(fn ($part) => e($part))->implode('<span class="sep">|</span>'))
            ->filter();
    @endphp

    <div class="watermark">
        @if($logo)
            <img src="{{ $logo }}" alt="">
        @else
            <div class="name">{{ $companyName }}</div>
        @endif
    </div>

    <header>
        <table>
            <tr>
                @if($logo)
                    <td class="logo"><img src="{{ $logo }}" alt="{{ $companyName }}"></td>
                @endif
                <td>
                    <div class="company">{{ $companyName }}</div>
                    <div class="contact">{!! $contact->implode('<br>') !!}</div>
                </td>
                <td class="doc">
                    <strong>{{ __('BILL OF QUANTITIES') }}</strong>
                    {{ __('Ref') }}: {{ $boq->reference ?: 'BOQ-'.$boq->id }}<br>
                    {{ __('Date') }}: {{ \App\Support\Format::date($boq->created_at) ?? '—' }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table width="100%">
            <tr>
                <td>{{ $companyName }} · {{ $boq->reference ?: 'BOQ-'.$boq->id }} · {{ __('Generated') }} {{ \App\Support\Format::date($generatedAt, true) }}</td>
                <td class="page" style="text-align:right"></td>
            </tr>
        </table>
    </footer>

    <h1>{{ $boq->name }}</h1>

    <table class="meta">
        <tr>
            <td class="label">{{ __('Project') }}</td>
            <td>{{ $project?->name ?? '—' }}{{ $project?->code ? ' ('.$project->code.')' : '' }}</td>
            <td class="label">{{ __('BOQ Reference') }}</td>
            <td>{{ $boq->reference ?: 'BOQ-'.$boq->id }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Client') }}</td>
            <td>{{ $project?->client ?: '—' }}</td>
            <td class="label">{{ __('Created') }}</td>
            <td>{{ \App\Support\Format::date($boq->created_at) ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Location') }}</td>
            <td>{{ collect([$project?->location, $project?->district, $project?->country])->filter()->implode(', ') ?: '—' }}</td>
            <td class="label">{{ __('Currency') }}</td>
            <td>{{ $currency }}</td>
        </tr>
        @if($project?->consultant || $project?->contractor)
            <tr>
                <td class="label">{{ __('Consultant') }}</td>
                <td>{{ $project?->consultant ?: '—' }}</td>
                <td class="label">{{ __('Contractor') }}</td>
                <td>{{ $project?->contractor ?: '—' }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:7%">{{ __('Item') }}</th>
                <th>{{ __('Description') }}</th>
                <th style="width:8%">{{ __('Unit') }}</th>
                <th class="num" style="width:10%">{{ __('Quantity') }}</th>
                <th class="num" style="width:14%">{{ __('Unit Price') }}</th>
                <th class="num" style="width:16%">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line['code'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['unit'] }}</td>
                    <td class="num">{{ \App\Support\Format::number($line['quantity'], 2) }}</td>
                    <td class="num {{ $line['priced'] ? '' : 'unpriced' }}">{{ $line['priced'] ? \App\Support\Format::number($line['rate'], 2) : __('Not priced') }}</td>
                    <td class="num">{{ \App\Support\Format::number($line['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:16px;color:#64748b">{{ __('This BOQ has no items yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="num">{{ $money($subtotal) }}</td></tr>
        @if($taxRate > 0)
            <tr><td>{{ $taxLabel }} ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</td><td class="num">{{ $money($tax) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('Grand Total') }}</td><td class="num">{{ $money($total) }}</td></tr>
    </table>

    <div class="notes">
        <strong>{{ __('Notes') }}</strong><br>
        @if(! empty($boq->description))
            {{ $boq->description }}<br>
        @endif
        {{ __('Rates are in :currency and are subject to market changes. Items marked "Not priced" are excluded from the totals.', ['currency' => $currency]) }}
    </div>

    <table class="signoff">
        <tr>
            <td>{{ __('Prepared by') }}: {{ $preparedBy ?: '—' }}<br>{{ $companyName }}</td>
            <td style="padding-left:24px">{{ __('Approved by') }}:<br>&nbsp;</td>
        </tr>
    </table>
</body>
</html>
