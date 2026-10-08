<?php

namespace Tests\Unit;

use App\Util\BillPeriods;
use PHPUnit\Framework\TestCase;

class BillPeriodsTest extends TestCase
{
    private function p(string $id, string $start, string $end, bool $advance = false): array
    {
        return ['id' => $id, 'start' => $start, 'end' => $end, 'advance' => $advance];
    }

    public function test_contiguous_periods_have_no_gaps(): void
    {
        $r = BillPeriods::analyze([$this->p('a', '2025-01-01', '2025-01-31'), $this->p('b', '2025-02-01', '2025-02-28')], '2025-02-28');
        $this->assertSame([], $r['gaps']);
        $this->assertSame([], $r['overlaps']);
        $this->assertNull($r['trailing']);
    }

    public function test_detects_gap_in_unsorted_input(): void
    {
        $r = BillPeriods::analyze([$this->p('c', '2025-03-01', '2025-03-31'), $this->p('a', '2025-01-01', '2025-01-31')], '2025-03-31');
        $this->assertSame([['from' => '2025-02-01', 'to' => '2025-02-28', 'after' => 'a', 'before' => 'c']], $r['gaps']);
    }

    public function test_detects_overlap(): void
    {
        $r = BillPeriods::analyze([$this->p('a', '2025-01-01', '2025-02-10'), $this->p('b', '2025-02-01', '2025-02-28')], '2025-02-28');
        $this->assertSame([['first' => 'a', 'second' => 'b']], $r['overlaps']);
        $this->assertSame([], $r['gaps']);
    }

    public function test_next_bill_starting_on_previous_end_date_is_contiguous(): void
    {
        $r = BillPeriods::analyze([
            $this->p('a', '2025-01-01', '2025-02-01'),
            $this->p('b', '2025-02-01', '2025-03-01'),
            $this->p('c', '2025-03-02', '2025-04-01'),
        ], '2025-04-01');
        $this->assertSame([], $r['overlaps']);
        $this->assertSame([], $r['gaps']);
    }

    public function test_start_one_day_before_previous_end_is_an_overlap(): void
    {
        $r = BillPeriods::analyze([$this->p('a', '2025-01-01', '2025-02-01'), $this->p('b', '2025-01-31', '2025-03-01')], '2025-03-01');
        $this->assertSame([['first' => 'a', 'second' => 'b']], $r['overlaps']);
    }

    public function test_long_period_does_not_hide_later_gap_or_false_positive(): void
    {
        $r = BillPeriods::analyze([
            $this->p('a', '2025-01-01', '2025-03-31'),
            $this->p('b', '2025-02-01', '2025-02-28'),
            $this->p('c', '2025-05-01', '2025-05-31'),
        ], '2025-05-31');
        $this->assertSame([['from' => '2025-04-01', 'to' => '2025-04-30', 'after' => 'a', 'before' => 'c']], $r['gaps']);
    }

    public function test_reports_trailing_period_not_yet_billed(): void
    {
        $r = BillPeriods::analyze([$this->p('a', '2025-01-01', '2025-01-31')], '2025-02-10');
        $this->assertSame(['from' => '2025-02-01', 'days' => 10], $r['trailing']);
    }

    public function test_empty_input(): void
    {
        $this->assertSame(['gaps' => [], 'overlaps' => [], 'trailing' => null], BillPeriods::analyze([], '2025-01-01'));
    }

    public function test_advance_invoices_inside_a_settlement_are_not_overlaps(): void
    {
        // Two advance invoices, then a settlement covering the whole stretch, as the sewage and water bills do
        $r = BillPeriods::analyze([
            $this->p('a1', '2024-09-05', '2024-10-29', true),
            $this->p('a2', '2024-10-29', '2024-12-27', true),
            $this->p('s', '2024-09-05', '2025-03-04'),
            $this->p('n', '2025-03-04', '2025-04-28'),
        ], '2025-04-28');
        $this->assertSame([], $r['overlaps']);
        $this->assertSame([], $r['gaps']);
    }

    public function test_without_the_advance_flag_the_same_periods_overlap(): void
    {
        $r = BillPeriods::analyze([
            $this->p('a1', '2024-09-05', '2024-10-29'),
            $this->p('s', '2024-09-05', '2025-03-04'),
        ], '2025-03-04');
        $this->assertCount(1, $r['overlaps']);
    }

    public function test_an_advance_sticking_out_of_the_period_or_two_overlapping_advances_are_still_reported(): void
    {
        $r = BillPeriods::analyze([
            $this->p('s', '2025-01-01', '2025-03-01'),
            $this->p('a', '2025-02-01', '2025-04-01', true),
        ], '2025-04-01');
        $this->assertCount(1, $r['overlaps']);

        $r = BillPeriods::analyze([
            $this->p('a', '2025-01-01', '2025-02-15', true),
            $this->p('b', '2025-02-01', '2025-03-01', true),
        ], '2025-03-01');
        $this->assertCount(1, $r['overlaps']);
    }

    public function test_an_advance_still_counts_towards_covering_time(): void
    {
        // An advance with no settlement yet covers its stretch, so a bill after it leaves no hole
        $r = BillPeriods::analyze([$this->p('a', '2026-06-26', '2026-08-28', true), $this->p('n', '2026-08-28', '2026-10-29')], '2026-10-29');
        $this->assertSame([], $r['gaps']);
        $this->assertSame([], $r['overlaps']);
    }
}
