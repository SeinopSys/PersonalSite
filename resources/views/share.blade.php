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
    @include('share-head')
</head>
<body>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1">{{ __('share.title') }}</h1>
            <div class="text-body-secondary">{{ __('share.as-of', ['date' => $date($report['generated'])]) }} · {{ __('share.invoice-count', ['count' => $report['bill_count']]) }}</div>
            @if($expiresAt)
                <div class="small text-body-secondary">{{ __('share.valid-until', ['date' => $date($expiresAt)]) }}</div>
            @endif
        </div>
        @include('share-theme-toggle')
    </div>

    <h2 class="h4 mt-4 mb-2">{{ __('share.discrepancies') }}</h2>
    @if(!$hasIssues)
        <p class="text-success-emphasis">{{ __('share.none') }}</p>
    @endif

    @if($report['multiple'])
        <h3 class="h5 mt-3 text-danger-emphasis">{{ __('share.multiple-heading', ['count' => count($report['multiple']), 'extra' => $money($report['extra_paid'])]) }}</h3>
        <div class="table-responsive"><table class="table table-bordered table-sm align-top">
            <thead><tr><th>{{ __('share.invoice') }}</th><th>{{ __('share.type') }}</th><th>{{ __('share.period') }}</th><th class="text-end text-nowrap">{{ __('share.amount') }}</th><th>{{ __('share.payments') }}</th><th class="text-end text-nowrap">{{ __('share.extra') }}</th></tr></thead>
            <tbody>
            @foreach($report['multiple'] as $row)
                <tr>
                    <td>{{ $row['invoice_number'] ?? '—' }}</td>
                    <td>{{ __('share.type-'.$row['type']) }}</td>
                    <td>{{ $period($row) }}</td>
                    <td class="text-end text-nowrap">{{ $money($row['amount']) }}</td>
                    <td>@foreach($row['payments'] as $p)<div>{{ $date($p['date']) }}: {{ $money($p['transfer']) }}@if($p['invoices'] > 1)<span class="text-body-secondary small"> ({{ __('share.covers', ['count' => $p['invoices']]) }})</span>@endif</div>@endforeach</td>
                    <td class="text-end text-nowrap text-danger-emphasis fw-semibold">{{ $money($row['extra']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif

    @if($report['gaps'])
        <h3 class="h5 mt-3 text-danger-emphasis">{{ __('share.gaps-heading') }}</h3>
        <ul>
            @foreach($report['gaps'] as $gap)
                <li>{{ __('share.type-'.$gap['type']) }}: <span class="text-danger-emphasis fw-semibold">{{ $date($gap['from']) }} – {{ $date($gap['to']) }}</span></li>
            @endforeach
        </ul>
    @endif

    @if($report['overlaps'])
        <h3 class="h5 mt-3 text-warning-emphasis">{{ __('share.overlaps-heading') }}</h3>
        <ul>
            @foreach($report['overlaps'] as $o)
                <li>{{ __('share.type-'.$o['type']) }}: {{ $o['first'] ?? '—' }} / {{ $o['second'] ?? '—' }}</li>
            @endforeach
        </ul>
    @endif

    @if($report['unpaid'])
        <h3 class="h5 mt-3 text-warning-emphasis">{{ __('share.unpaid-heading') }}</h3>
        <ul>
            @foreach($report['unpaid'] as $row)
                <li>{{ __('share.type-'.$row['type']) }}, {{ $period($row) }}: {{ $money($row['amount']) }} ({{ $row['invoice_number'] ?? '—' }})</li>
            @endforeach
        </ul>
    @endif

    <h2 class="h4 mt-4 mb-2">{{ __('share.all-invoices') }}</h2>
    @foreach($report['types'] as $type => $rows)
        <h3 class="h5 mt-3">{{ __('share.type-'.$type) }}</h3>
        <div class="table-responsive"><table class="table table-bordered table-sm align-top">
            <thead><tr><th>{{ __('share.invoice') }}</th><th>{{ __('share.period') }}</th><th class="text-end text-nowrap">{{ __('share.amount') }}</th><th>{{ __('share.due') }}</th><th>{{ __('share.status') }}</th><th>{{ __('share.payments') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                @if($row['gap_before'])
                    <tr><td colspan="6" class="text-danger-emphasis fw-semibold">{{ __('share.missing-invoice', ['from' => $date($row['gap_before']['from']), 'to' => $date($row['gap_before']['to'])]) }}</td></tr>
                @endif
                <tr>
                    <td>
                        {{ $row['invoice_number'] ?? '—' }}
                        @if($row['advance'])<div class="text-body-secondary small">{{ __('share.advance') }}</div>@endif
                        @if($row['settlement_covers'] > 0)<div class="text-body-secondary small">{{ __('share.settlement', ['count' => $row['settlement_covers']]) }}</div>@endif
                    </td>
                    <td>{{ $period($row) }}@if($row['overlaps'])<div class="text-warning-emphasis fw-semibold small">{{ __('share.overlap') }}</div>@endif</td>
                    <td class="text-end text-nowrap">{{ $money($row['amount']) }}</td>
                    <td>{{ $date($row['due_date']) }}</td>
                    <td>
                        @if($row['status'] === 'paid')<span class="text-success-emphasis">{{ __('share.paid') }}</span>
                        @elseif($row['status'] === 'overdue')<span class="text-danger-emphasis fw-semibold">{{ __('share.overdue') }}</span>
                        @else<span class="text-warning-emphasis fw-semibold">{{ __('share.unpaid') }}</span>@endif
                        @if($row['times_paid'] > 1)<div class="text-danger-emphasis fw-semibold small">{{ __('share.paid-times', ['count' => $row['times_paid']]) }}</div>@endif
                    </td>
                    <td>@forelse($row['payments'] as $p)<div>{{ $date($p['date']) }}: {{ $money($p['transfer']) }}@if($p['invoices'] > 1)<span class="text-body-secondary small"> ({{ __('share.covers', ['count' => $p['invoices']]) }})</span>@endif</div>@empty<span class="text-body-secondary">—</span>@endforelse</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endforeach

    <p class="mt-4 text-body-secondary small">{{ __('share.footnote') }}</p>
</main>
</body>
</html>
