<?php

namespace Tests\Unit;

use App\Util\Reconciliation;
use PHPUnit\Framework\TestCase;

class ReconciliationTest extends TestCase
{
    public function test_max_fee_is_two_percent_with_a_minimum(): void
    {
        $this->assertSame(500, Reconciliation::maxFeeFor(1000));
        $this->assertSame(500, Reconciliation::maxFeeFor(25000));
        $this->assertSame(2000, Reconciliation::maxFeeFor(100000));
    }

    public function test_the_payment_may_be_a_fee_above_the_bills_or_rounding_below_them(): void
    {
        // 10,000 Ft of bills
        $this->assertTrue(Reconciliation::isWhollyCoveredByBills(10000, 10000));
        $this->assertTrue(Reconciliation::isWhollyCoveredByBills(10029, 10000));
        $this->assertTrue(Reconciliation::isWhollyCoveredByBills(10500, 10000), 'a 500 Ft fee is the limit for a small payment');
        $this->assertFalse(Reconciliation::isWhollyCoveredByBills(10501, 10000));
        $this->assertTrue(Reconciliation::isWhollyCoveredByBills(9800, 10000), 'bills 200 Ft over is rounding');
        $this->assertFalse(Reconciliation::isWhollyCoveredByBills(9799, 10000));
    }

    public function test_a_payment_mostly_unexplained_by_its_bills_is_not_covered(): void
    {
        $this->assertFalse(Reconciliation::isWhollyCoveredByBills(54460, 20556));
        $this->assertFalse(Reconciliation::isWhollyCoveredByBills(8649, 32186));
    }
}
