<?php

declare(strict_types=1);

namespace App\Util;

/**
 * The same rules the bills page applies in the browser (resources/js/bills/matching.ts and reconcile.ts): what counts
 * as a transfer fee and as rounding when comparing a payment with the bills it paid.
 */
final class Reconciliation
{
    /** Differences up to this many Ft, either way, are rounding (credits, rounded-up payments), not a discrepancy. */
    public const ROUNDING_TOLERANCE = 200;

    /** The most a transfer may exceed the bills it paid and still be explained by a bank fee. */
    public static function maxFeeFor(int $amount): int
    {
        return max(500, (int) round($amount * 0.02));
    }

    /** True when the bills explain the whole payment: the rest is only a plausible fee, or rounding. */
    public static function isWhollyCoveredByBills(int $amount, int $billsTotal): bool
    {
        $remainder = $amount - $billsTotal;

        return $remainder >= -self::ROUNDING_TOLERANCE && $remainder <= self::maxFeeFor($amount);
    }
}
