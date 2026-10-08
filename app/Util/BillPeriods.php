<?php

declare(strict_types=1);

namespace App\Util;

use Carbon\CarbonImmutable;

class BillPeriods
{
    /**
     * Finds holes and overlaps in the accounting periods of a single bill type.
     *
     * An advance invoice (részszámla) inside the period of another, non-advance bill is expected: the later settlement
     * bill covers the whole stretch and credits the advances. That pairing is not reported as an overlap.
     *
     * @param  array<int, array{id: string, start: string, end: string, advance?: bool}>  $periods  Y-m-d dates, in any order
     * @param  string  $today  Y-m-d, used to determine whether the latest bill is out of date
     *
     * @return array{
     *     gaps: array<int, array{from: string, to: string, after: string, before: string}>,
     *     overlaps: array<int, array{first: string, second: string}>,
     *     trailing: array{from: string, days: int}|null
     * }
     */
    public static function analyze(array $periods, string $today): array
    {
        usort($periods, fn (array $a, array $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $overlaps = [];
        foreach ($periods as $j => $later) {
            for ($i = 0; $i < $j; $i++) {
                $earlier = $periods[$i];
                // Sorted by start, so they overlap when the later one starts before the earlier one ends. Sharing the
                // boundary day (end == start) is contiguous, not an overlap.
                if ($later['start'] < $earlier['end'] && !self::isExpectedNesting($earlier, $later)) {
                    $overlaps[] = ['first' => $earlier['id'], 'second' => $later['id']];
                }
            }
        }

        // Holes are judged on the union of all periods, advances included
        $gaps = [];
        $latest = null;
        foreach ($periods as $period) {
            if ($latest !== null) {
                $end = CarbonImmutable::parse($latest['end']);
                $start = CarbonImmutable::parse($period['start']);
                if ($start->gt($end->addDay())) {
                    $gaps[] = [
                        'from' => $end->addDay()->toDateString(),
                        'to' => $start->subDay()->toDateString(),
                        'after' => $latest['id'],
                        'before' => $period['id'],
                    ];
                }
            }
            // Keep whichever period reaches furthest so a long bill covering a short one doesn't hide a later gap
            if ($latest === null || $period['end'] > $latest['end']) {
                $latest = $period;
            }
        }

        $trailing = null;
        if ($latest !== null) {
            $nextDay = CarbonImmutable::parse($latest['end'])->addDay();
            $now = CarbonImmutable::parse($today);
            if ($now->gte($nextDay)) {
                $trailing = ['from' => $nextDay->toDateString(), 'days' => (int) $nextDay->diffInDays($now) + 1];
            }
        }

        return ['gaps' => $gaps, 'overlaps' => $overlaps, 'trailing' => $trailing];
    }

    /** An advance invoice lying entirely inside the period of a bill that is not an advance itself. */
    private static function isExpectedNesting(array $a, array $b): bool
    {
        foreach ([[$a, $b], [$b, $a]] as [$advance, $container]) {
            if (!empty($advance['advance']) && empty($container['advance'])
                && $advance['start'] >= $container['start'] && $advance['end'] <= $container['end']) {
                return true;
            }
        }

        return false;
    }
}
