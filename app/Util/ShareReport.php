<?php

declare(strict_types=1);

namespace App\Util;

use App\Models\Bill;
use App\Models\User;

/**
 * The per-invoice overview shown on a public share link. Deliberately limited to invoices and the payments linked to
 * them: no bank details, notes, fees, or transfers that aren't tied to a bill.
 */
class ShareReport
{
    /**
     * @return array{
     *     generated: string,
     *     bill_count: int,
     *     types: array<string, array<int, array<string, mixed>>>,
     *     multiple: array<int, array<string, mixed>>,
     *     gaps: array<int, array{type: string, from: string, to: string}>,
     *     overlaps: array<int, array{type: string, first: string|null, second: string|null}>,
     *     unpaid: array<int, array<string, mixed>>,
     *     extra_paid: int
     * }
     */
    public static function build(User $user, string $today): array
    {
        $bills = $user->bills()->with(['transactions' => fn ($q) => $q->withCount('bills')])->get();

        $report = [
            'generated' => $today,
            'bill_count' => $bills->count(),
            'types' => [],
            'multiple' => [],
            'gaps' => [],
            'overlaps' => [],
            'unpaid' => [],
            'extra_paid' => 0,
        ];

        foreach (Bill::TYPES as $type) {
            $typed = $bills->where('type', $type)->sort(fn (Bill $a, Bill $b) => [$a->period_start->toDateString(), $a->period_end->toDateString()]
                <=> [$b->period_start->toDateString(), $b->period_end->toDateString()])->values();
            if ($typed->isEmpty()) {
                continue;
            }

            $analysis = BillPeriods::analyze($typed->map(fn (Bill $b) => [
                'id' => $b->id,
                'start' => $b->period_start->toDateString(),
                'end' => $b->period_end->toDateString(),
                'advance' => $b->advance,
            ])->all(), $today);
            $gapBefore = collect($analysis['gaps'])->keyBy('before');
            $byId = $typed->keyBy('id');
            $overlapping = collect($analysis['overlaps'])->pluck('second')->flip();

            foreach ($analysis['gaps'] as $gap) {
                $report['gaps'][] = ['type' => $type, 'from' => $gap['from'], 'to' => $gap['to']];
            }
            foreach ($analysis['overlaps'] as $overlap) {
                $report['overlaps'][] = [
                    'type' => $type,
                    'first' => $byId[$overlap['first']]->invoice_number,
                    'second' => $byId[$overlap['second']]->invoice_number,
                ];
            }

            $rows = [];
            foreach ($typed as $bill) {
                $payments = $bill->transactions->sortBy(fn ($t) => $t->date->toDateString())->map(fn ($t) => [
                    'date' => $t->date->toDateString(),
                    'transfer' => $t->amount,
                    'invoices' => $t->bills_count,
                ])->values()->all();
                $times = count($payments);
                $due = $bill->due_date?->toDateString();
                $status = $times > 0 ? 'paid' : ($due !== null && $due < $today ? 'overdue' : 'unpaid');

                $row = [
                    'invoice_number' => $bill->invoice_number,
                    'advance' => $bill->advance,
                    'settlement_covers' => $bill->advance ? 0 : $typed->filter(fn (Bill $a) => $a->advance
                        && $a->period_start->toDateString() >= $bill->period_start->toDateString()
                        && $a->period_end->toDateString() <= $bill->period_end->toDateString())->count(),
                    'period_start' => $bill->period_start->toDateString(),
                    'period_end' => $bill->period_end->toDateString(),
                    'amount' => $bill->amount,
                    'due_date' => $due,
                    'status' => $status,
                    'times_paid' => $times,
                    'payments' => $payments,
                    'overlaps' => $overlapping->has($bill->id),
                    'gap_before' => isset($gapBefore[$bill->id]) ? ['from' => $gapBefore[$bill->id]['from'], 'to' => $gapBefore[$bill->id]['to']] : null,
                ];
                $rows[] = $row;

                if ($times > 1) {
                    $extra = $bill->amount * ($times - 1);
                    $report['extra_paid'] += $extra;
                    $report['multiple'][] = $row + ['type' => $type, 'extra' => $extra];
                }
                if ($status !== 'paid') {
                    $report['unpaid'][] = $row + ['type' => $type];
                }
            }
            $report['types'][$type] = $rows;
        }

        return $report;
    }
}
