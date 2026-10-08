@php
    $date = fn (?string $d) => $d ? \Carbon\Carbon::parse($d)->format('Y. m. d.') : '—';
    $money = fn (int $n) => number_format($n, 0, ',', "\u{a0}")."\u{a0}Ft";
    $period = fn (array $r) => $date($r['period_start']).' – '.$date($r['period_end']);
    $hasIssues = $report['multiple'] || $report['gaps'] || $report['overlaps'] || $report['unpaid'];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('share.title') }}</title>
    <style>
        :root { --bg:#fff; --fg:#212529; --muted:#6c757d; --line:#dee2e6; --bad:#b02a37; --warn:#997404; --ok:#146c43; --info:#087990; }
        @media (prefers-color-scheme: dark) { :root { --bg:#16181b; --fg:#e4e6e8; --muted:#9aa1a8; --line:#33373b; --bad:#ea868f; --warn:#ffda6a; --ok:#75b798; --info:#6edff6; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        main { max-width: 64rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.2rem; margin: 2rem 0 .5rem; }
        h3 { font-size: 1rem; margin: 1.25rem 0 .25rem; }
        .muted { color: var(--muted); } .bad { color: var(--bad); font-weight: 600; } .warn { color: var(--warn); font-weight: 600; }
        .ok { color: var(--ok); } .info { color: var(--info); } .small { font-size: .875rem; } .num { text-align: right; white-space: nowrap; }
        .scroll { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: .5rem 0 1rem; }
        th, td { border: 1px solid var(--line); padding: .35rem .6rem; text-align: left; vertical-align: top; }
        th { font-weight: 600; } td.num, th.num { text-align: right; }
        ul { margin: .25rem 0 1rem 1.25rem; padding: 0; }
        p.note { margin: 2rem 0 0; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('share.title') }}</h1>
    <div class="muted">{{ __('share.as-of', ['date' => $date($report['generated'])]) }} · {{ __('share.invoice-count', ['count' => $report['bill_count']]) }}</div>

    <h2>{{ __('share.discrepancies') }}</h2>
    @if(!$hasIssues)
        <p class="ok">{{ __('share.none') }}</p>
    @endif

    @if($report['multiple'])
        <h3 class="bad">{{ __('share.multiple-heading', ['count' => count($report['multiple']), 'extra' => $money($report['extra_paid'])]) }}</h3>
        <div class="scroll"><table>
            <thead><tr><th>{{ __('share.invoice') }}</th><th>{{ __('share.type') }}</th><th>{{ __('share.period') }}</th><th class="num">{{ __('share.amount') }}</th><th>{{ __('share.payments') }}</th><th class="num">{{ __('share.extra') }}</th></tr></thead>
            <tbody>
            @foreach($report['multiple'] as $row)
                <tr>
                    <td>{{ $row['invoice_number'] ?? '—' }}</td>
                    <td>{{ __('share.type-'.$row['type']) }}</td>
                    <td>{{ $period($row) }}</td>
                    <td class="num">{{ $money($row['amount']) }}</td>
                    <td>@foreach($row['payments'] as $p)<div>{{ $date($p['date']) }}: {{ $money($p['transfer']) }}@if($p['invoices'] > 1)<span class="muted small"> ({{ __('share.covers', ['count' => $p['invoices']]) }})</span>@endif</div>@endforeach</td>
                    <td class="num bad">{{ $money($row['extra']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif

    @if($report['gaps'])
        <h3 class="bad">{{ __('share.gaps-heading') }}</h3>
        <ul>
            @foreach($report['gaps'] as $gap)
                <li>{{ __('share.type-'.$gap['type']) }}: <span class="bad">{{ $date($gap['from']) }} – {{ $date($gap['to']) }}</span></li>
            @endforeach
        </ul>
    @endif

    @if($report['overlaps'])
        <h3 class="warn">{{ __('share.overlaps-heading') }}</h3>
        <ul>
            @foreach($report['overlaps'] as $o)
                <li>{{ __('share.type-'.$o['type']) }}: {{ $o['first'] ?? '—' }} / {{ $o['second'] ?? '—' }}</li>
            @endforeach
        </ul>
    @endif

    @if($report['unpaid'])
        <h3 class="warn">{{ __('share.unpaid-heading') }}</h3>
        <ul>
            @foreach($report['unpaid'] as $row)
                <li>{{ __('share.type-'.$row['type']) }}, {{ $period($row) }}: {{ $money($row['amount']) }} ({{ $row['invoice_number'] ?? '—' }})</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ __('share.all-invoices') }}</h2>
    @foreach($report['types'] as $type => $rows)
        <h3>{{ __('share.type-'.$type) }}</h3>
        <div class="scroll"><table>
            <thead><tr><th>{{ __('share.invoice') }}</th><th>{{ __('share.period') }}</th><th class="num">{{ __('share.amount') }}</th><th>{{ __('share.due') }}</th><th>{{ __('share.status') }}</th><th>{{ __('share.payments') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                @if($row['gap_before'])
                    <tr><td colspan="6" class="bad">{{ __('share.missing-invoice', ['from' => $date($row['gap_before']['from']), 'to' => $date($row['gap_before']['to'])]) }}</td></tr>
                @endif
                <tr>
                    <td>
                        {{ $row['invoice_number'] ?? '—' }}
                        @if($row['advance'])<div class="muted small">{{ __('share.advance') }}</div>@endif
                        @if($row['settlement_covers'] > 0)<div class="muted small">{{ __('share.settlement', ['count' => $row['settlement_covers']]) }}</div>@endif
                    </td>
                    <td>{{ $period($row) }}@if($row['overlaps'])<div class="warn small">{{ __('share.overlap') }}</div>@endif</td>
                    <td class="num">{{ $money($row['amount']) }}</td>
                    <td>{{ $date($row['due_date']) }}</td>
                    <td>
                        @if($row['status'] === 'paid')<span class="ok">{{ __('share.paid') }}</span>
                        @elseif($row['status'] === 'overdue')<span class="bad">{{ __('share.overdue') }}</span>
                        @else<span class="warn">{{ __('share.unpaid') }}</span>@endif
                        @if($row['times_paid'] > 1)<div class="bad small">{{ __('share.paid-times', ['count' => $row['times_paid']]) }}</div>@endif
                    </td>
                    <td>@forelse($row['payments'] as $p)<div>{{ $date($p['date']) }}: {{ $money($p['transfer']) }}@if($p['invoices'] > 1)<span class="muted small"> ({{ __('share.covers', ['count' => $p['invoices']]) }})</span>@endif</div>@empty<span class="muted">—</span>@endforelse</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endforeach

    <p class="note muted small">{{ __('share.footnote') }}</p>
</main>
</body>
</html>
