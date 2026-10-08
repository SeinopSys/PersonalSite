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
     *     extra_paid: int,
     *     transfers: array<int, array<string, mixed>>
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
            'transfers' => self::transfers($user),
        ];

        // An overpayment settled by credit: what each invoice's overpayment has been credited towards, and where a
        // credit came from, so credited overpayments stop counting as extra payments
        $numberById = $bills->mapWithKeys(fn (Bill $b) => [$b->id => $b->invoice_number]);
        $creditedFrom = $bills->filter(fn (Bill $b) => $b->credit_source_id !== null && ($b->credit_applied ?? 0) > 0)->groupBy('credit_source_id');

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
                    'credit_applied' => $bill->credit_applied ?? 0,
                    'credit_source' => $bill->credit_source_id ? ($numberById[$bill->credit_source_id] ?? null) : null,
                    'credited_out' => (int) $creditedFrom->get($bill->id, collect())->sum('credit_applied'),
                    'credited_to' => $creditedFrom->get($bill->id, collect())->map(fn (Bill $b) => $b->invoice_number)->values()->all(),
                    'due_date' => $due,
                    'status' => $status,
                    'times_paid' => $times,
                    'payments' => $payments,
                    'overlaps' => $overlapping->has($bill->id),
                    'gap_before' => isset($gapBefore[$bill->id]) ? ['from' => $gapBefore[$bill->id]['from'], 'to' => $gapBefore[$bill->id]['to']] : null,
                ];
                $rows[] = $row;

                if ($times > 1) {
                    // What was paid beyond the invoice, less whatever the landlord has already credited towards another bill
                    $gross = $bill->amount * ($times - 1);
                    $net = max(0, $gross - $row['credited_out']);
                    if ($net > 0) {
                        $report['extra_paid'] += $net;
                        $report['multiple'][] = $row + ['type' => $type, 'extra' => $net, 'credited' => min($row['credited_out'], $gross)];
                    }
                }
                if ($status !== 'paid') {
                    $report['unpaid'][] = $row + ['type' => $type];
                }
            }
            $report['types'][$type] = $rows;
        }

        return $report;
    }

    /**
     * Payments that the linked bills cover in full, one entry per payment. Transfers in a group (a bill paid in
     * instalments) are one payment. Anything marked as accounted for, with an accounted-for amount, without bills, or
     * only partly explained by bills is left out: those aren't utility payments the landlord's invoices account for.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function transfers(User $user): array
    {
        $units = $user->bankTransactions()->with('bills')->get()->groupBy(fn ($t) => $t->group_id ?? $t->id);

        $transfers = [];
        foreach ($units as $members) {
            $members = $members->sort(fn ($a, $b) => [$a->date->toDateString(), $a->amount] <=> [$b->date->toDateString(), $b->amount])->values();
            if ($members->contains(fn ($t) => $t->accounted || ($t->accounted_amount ?? 0) > 0)) {
                continue;
            }
            $bills = $members->flatMap(fn ($t) => $t->bills)->unique('id')->sort(fn (Bill $a, Bill $b) => [$a->period_start->toDateString(), $a->type]
                <=> [$b->period_start->toDateString(), $b->type])->values();
            $amount = $members->sum('amount');
            // What had to be transferred: bills less any credit carried over from an earlier overpayment
            $payable = $bills->sum(fn (Bill $b) => $b->payableAmount());
            if ($bills->isEmpty() || !Reconciliation::isWhollyCoveredByBills($amount, $payable)) {
                continue;
            }

            $transfers[] = [
                'payments' => $members->map(fn ($t) => ['date' => $t->date->toDateString(), 'amount' => $t->amount])->all(),
                'amount' => $amount,
                'invoices' => $bills->map(fn (Bill $b) => [
                    'invoice_number' => $b->invoice_number,
                    'type' => $b->type,
                    'period_start' => $b->period_start->toDateString(),
                    'period_end' => $b->period_end->toDateString(),
                    'amount' => $b->amount,
                    'credit_applied' => $b->credit_applied ?? 0,
                ])->all(),
                'invoices_total' => $payable,
                'has_credit' => $bills->contains(fn (Bill $b) => ($b->credit_applied ?? 0) > 0),
            ];
        }
        usort($transfers, fn ($a, $b) => [$a['payments'][0]['date'], $a['amount']] <=> [$b['payments'][0]['date'], $b['amount']]);

        return $transfers;
    }
}
