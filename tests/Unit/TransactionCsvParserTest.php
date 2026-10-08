<?php

namespace Tests\Unit;

use App\Util\TransactionCsvParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TransactionCsvParserTest extends TestCase
{
    private const HEADER = "Számlaszám,Ellenoldali számlaszám,Forgalom típusa,Banki azonosító,Tranzakció időpontja,Könyvelés dátuma,Összeg,Devizanem\n";

    public function test_folds_fee_row_into_payment(): void
    {
        $csv = self::HEADER
            ."1,2,ESETI MEGBÍZÁSOK KÖLTSÉGE,100000000000000002,2026-10-08 12:09:00,2026-10-08,- 118,HUF\n"
            ."1,2,AZONNALI FIZETÉS,100000000000000001,2026-10-08 12:09:22,2026-10-08,-33\u{a0}847,HUF\n";
        $r = TransactionCsvParser::parse($csv);

        $this->assertSame([[
            'external_id' => '100000000000000001', 'date' => '2026-10-08', 'amount' => 33965, 'type' => 'AZONNALI FIZETÉS',
        ]], $r['payments']);
        $this->assertSame([], $r['skipped']);
    }

    public function test_columns_are_found_by_name_not_position(): void
    {
        $csv = "Összeg;Devizanem;Tranzakció időpontja;Banki azonosító\n-1 500;HUF;2026-01-02 10:00:00;A1\n";
        $r = TransactionCsvParser::parse($csv);
        $this->assertSame('2026-01-02', $r['payments'][0]['date']);
        $this->assertSame(1500, $r['payments'][0]['amount']);
        $this->assertSame('', $r['payments'][0]['type']);
    }

    public function test_skips_incoming_foreign_currency_and_orphan_fees(): void
    {
        $csv = self::HEADER
            ."1,2,ÁTUTALÁS,1,2026-01-01 10:00:00,2026-01-01,5 000,HUF\n"
            ."1,2,ÁTUTALÁS,2,2026-01-01 11:00:00,2026-01-01,-5,EUR\n"
            ."1,2,KÖLTSÉG,3,2026-01-01 12:00:00,2026-01-01,-10,HUF\n";
        $r = TransactionCsvParser::parse($csv);
        $this->assertSame([], $r['payments']);
        $this->assertEquals(['incoming' => 1, 'non-HUF' => 1, 'fee without payment' => 1], $r['skipped']);
    }

    public function test_fee_pairs_with_closest_preceding_payment(): void
    {
        $csv = self::HEADER
            ."1,2,AZONNALI FIZETÉS,10,2026-01-01 10:00:10,2026-01-01,-1 000,HUF\n"
            ."1,2,AZONNALI FIZETÉS,12,2026-01-01 10:00:40,2026-01-01,-2 000,HUF\n"
            ."1,2,KÖLTSÉG,13,2026-01-01 10:00:00,2026-01-01,-7,HUF\n";
        $payments = TransactionCsvParser::parse($csv)['payments'];
        $this->assertSame([1000, 2007], array_column($payments, 'amount'));
    }

    public function test_fee_booked_hours_later_pairs_by_adjacent_id(): void
    {
        $csv = self::HEADER
            ."1,2,AZONNALI ÁTUTALÁS,202601012251004856,2026-01-01 22:51:11,2026-01-01,-20 604,HUF\n"
            ."1,2,ESETI MEGBÍZÁSOK KÖLTSÉGE,202601020055004857,2026-01-02 00:55:00,2026-01-02,- 41,HUF\n"
            ."1,2,AZONNALI ÁTUTALÁS,202601051000004900,2026-01-05 10:00:00,2026-01-05,-1 000,HUF\n";
        $r = TransactionCsvParser::parse($csv);

        $this->assertSame([20645, 1000], array_column($r['payments'], 'amount'));
        $this->assertSame([], $r['skipped']);
    }

    public function test_adjacent_id_far_apart_in_time_is_not_paired(): void
    {
        $csv = self::HEADER
            ."1,2,AZONNALI ÁTUTALÁS,300000000000000001,2026-01-01 10:00:00,2026-01-01,-1 000,HUF\n"
            ."1,2,KÖLTSÉG,300000000000000002,2026-02-01 10:00:00,2026-02-01,-5,HUF\n";
        $r = TransactionCsvParser::parse($csv);
        $this->assertSame([1000], array_column($r['payments'], 'amount'));
        $this->assertSame(['fee without payment' => 1], $r['skipped']);
    }

    public function test_requires_essential_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransactionCsvParser::parse("Foo,Bar\n1,2\n");
    }

    public function test_parse_amount_variants(): void
    {
        $this->assertSame(-33847, TransactionCsvParser::parseAmount("-33\u{a0}847"));
        $this->assertSame(-118, TransactionCsvParser::parseAmount("- 118"));
        $this->assertSame(1234, TransactionCsvParser::parseAmount('1.234'));
        $this->assertSame(1235, TransactionCsvParser::parseAmount('1.234,50'));
        $this->assertNull(TransactionCsvParser::parseAmount('abc'));
    }
}
