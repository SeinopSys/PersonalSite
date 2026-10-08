<?php

declare(strict_types=1);

namespace App\Util;

use InvalidArgumentException;

/**
 * Reads a bank statement CSV export into payments. Columns are found by matching header names against
 * patterns rather than by position, so exports that reorder or add columns keep working.
 *
 * Banks list the transfer fee as a separate row right after the payment it belongs to; those rows are
 * folded into the payment so the stored amount is what actually left the account.
 */
class TransactionCsvParser
{
    /** Header patterns per field, in priority order: the first pattern that matches any header wins. */
    private const COLUMNS = [
        'id' => ['/azonos[ií]t[óo]/iu', '/\b(transaction|reference)\b.*\bid\b/i', '/^id$/i'],
        'date' => ['/tranzakci[óo].*id[őo]pont/iu', '/\b(transaction|booking|value)\b.*\bdate\b/i', '/id[őo]pont/iu', '/d[áa]tum/iu', '/date/i'],
        'amount' => ['/[öo]sszeg/iu', '/amount/i'],
        'type' => ['/forgalom.*t[íi]pus/iu', '/t[íi]pus/iu', '/type/i'],
        'currency' => ['/deviza/iu', '/currency/i'],
    ];

    /** Row types (matched against the type column) that are fees attached to a payment. */
    private const FEE_PATTERN = '/k[öo]lts[ée]g|d[íi]j\b|\bfee\b/iu';

    /**
     * @return array{
     *     payments: array<int, array{external_id: string, date: string, amount: int, type: string}>,
     *     skipped: array<string, int>
     * }
     */
    public static function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\R/u', trim($csv));
        if (count($lines) < 2) {
            throw new InvalidArgumentException('The file has no data rows.');
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = str_getcsv(array_shift($lines), $delimiter, '"', '');
        $columns = self::mapColumns($headers);

        $skipped = [];
        $skip = function (string $reason) use (&$skipped) {
            $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
        };

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $currency = isset($columns['currency']) ? strtoupper(trim($cells[$columns['currency']] ?? '')) : 'HUF';
            if ($currency !== '' && $currency !== 'HUF') {
                $skip('non-HUF');
                continue;
            }
            $amount = self::parseAmount($cells[$columns['amount']] ?? '');
            $date = self::parseDate($cells[$columns['date']] ?? '');
            $id = trim($cells[$columns['id']] ?? '');
            if ($amount === null || $date === null || $id === '') {
                $skip('unreadable');
                continue;
            }
            $type = trim($cells[$columns['type'] ?? -1] ?? '');
            $rows[] = [
                'external_id' => $id,
                'date' => $date['date'],
                'minute' => $date['minute'],
                'amount' => $amount,
                'type' => $type,
                'fee' => $type !== '' && preg_match(self::FEE_PATTERN, $type) === 1,
            ];
        }

        // Fees may precede or follow their payment in the file, so pair on the timestamp (to the minute) and id order
        $payments = [];
        $fees = [];
        foreach ($rows as $row) {
            if ($row['fee']) {
                $fees[] = $row;
            } elseif ($row['amount'] < 0) {
                $payments[] = $row + ['feeTotal' => 0];
            } else {
                $skip('incoming');
            }
        }
        foreach ($fees as $fee) {
            $target = self::findPaymentForFee($fee, $payments);
            if ($target === null) {
                $skip('fee without payment');
                continue;
            }
            $payments[$target]['feeTotal'] += $fee['amount'];
        }

        return [
            'payments' => array_map(fn (array $p) => [
                'external_id' => $p['external_id'],
                'date' => $p['date'],
                // Outflows are negative in the export; bills and transactions here are positive amounts
                'amount' => -($p['amount'] + $p['feeTotal']),
                'type' => $p['type'],
            ], $payments),
            'skipped' => $skipped,
        ];
    }

    /**
     * Banks usually book the fee under the very next id, but may time-stamp it hours later (even past midnight),
     * so an adjacent id within a day wins. Otherwise fall back to the closest earlier payment from the same minute.
     *
     * @param  array<int, array>  $payments
     */
    private static function findPaymentForFee(array $fee, array $payments): ?int
    {
        $feeDay = strtotime($fee['date']);
        $sameMinute = null;
        foreach ($payments as $i => $payment) {
            if (self::isNextId($payment['external_id'], $fee['external_id']) && abs($feeDay - strtotime($payment['date'])) <= 86400) {
                return $i;
            }
            if ($payment['minute'] === $fee['minute'] && strcmp($payment['external_id'], $fee['external_id']) < 0
                && ($sameMinute === null || strcmp($payment['external_id'], $payments[$sameMinute]['external_id']) > 0)) {
                $sameMinute = $i;
            }
        }

        return $sameMinute;
    }

    /**
     * Bank ids often embed a timestamp followed by a running counter (yyyymmddHHMM + 6-digit sequence), so a fee booked
     * hours after its payment differs in the timestamp part too. Compare the whole id and, failing that, the trailing counter.
     */
    private static function isNextId(string $earlier, string $later): bool
    {
        if (!ctype_digit($earlier) || !ctype_digit($later) || strlen($earlier) !== strlen($later)) {
            return false;
        }
        if ((int) $later - (int) $earlier === 1) {
            return true;
        }
        $counterLength = 6;

        return strlen($later) > $counterLength
            && (int) substr($later, -$counterLength) - (int) substr($earlier, -$counterLength) === 1;
    }

    /**
     * @return array<string, int> field => column index
     */
    private static function mapColumns(array $headers): array
    {
        $map = [];
        foreach (self::COLUMNS as $field => $patterns) {
            foreach ($patterns as $pattern) {
                foreach ($headers as $index => $header) {
                    if (in_array($index, $map, true)) {
                        continue;
                    }
                    if (preg_match($pattern, (string) $header) === 1) {
                        $map[$field] = $index;
                        continue 3;
                    }
                }
            }
        }
        $missing = array_diff(['id', 'date', 'amount'], array_keys($map));
        if ($missing) {
            throw new InvalidArgumentException('Could not find a column for: '.implode(', ', $missing));
        }

        return $map;
    }

    /** Handles "-33 847", "- 118" (with NBSP) and "1.234,50"-style values; HUF has no minor unit so decimals are rounded. */
    public static function parseAmount(string $raw): ?int
    {
        $clean = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $raw);
        if ($clean === '' || preg_match('/^[-+]?[\d.,]+$/', $clean) !== 1) {
            return null;
        }
        $decimal = preg_match('/[.,](\d{1,2})$/', $clean) === 1;
        if ($decimal) {
            $clean = preg_replace('/[.,](?=\d{1,2}$)/', '#', $clean);
            [$whole, $fraction] = explode('#', $clean);
            $whole = preg_replace('/[.,]/', '', $whole);

            return (int) round((float) "$whole.$fraction");
        }

        return (int) preg_replace('/[.,]/', '', $clean);
    }

    /** @return array{date: string, minute: string}|null */
    private static function parseDate(string $raw): ?array
    {
        if (preg_match('/(\d{4})[-.\/]\s?(\d{2})[-.\/]\s?(\d{2})\.?(?:[ T]+(\d{2}):(\d{2}))?/', $raw, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        $date = "$m[1]-$m[2]-$m[3]";

        return ['date' => $date, 'minute' => $date.' '.($m[4] ?? '00').':'.($m[5] ?? '00')];
    }
}
