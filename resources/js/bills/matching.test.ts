/* eslint-disable import/no-extraneous-dependencies */
import { describe, expect, it } from 'vitest';
import { suggestMatches } from './matching';
import { BankTransaction, Bill } from './types';

let n = 0;
const bill = (amount: number, fileModified: string | null, extra: Partial<Bill> = {}): Bill => {
  n += 1;
  return {
    id: `b${n}`,
    type: 'water',
    sha256: null,
    invoice_number: null,
    period_start: '2025-01-01',
    period_end: '2025-01-31',
    due_date: null,
    file_modified_at: fileModified,
    advance: false,
    credit_applied: 0,
    credit_source_id: null,
    amount,
    transaction_ids: [],
    ...extra,
  };
};
const tx = (id: string, date: string, amount: number, billIds: string[] = []): BankTransaction => ({
  id, date, amount, note: null, accounted: false, accounted_amount: null, group_id: null, bill_ids: billIds,
});

describe('suggestMatches', () => {
  it('matches a single bill to a nearby transaction, with the fee left over', () => {
    const b = bill(10000, '2025-02-10');
    const result = suggestMatches([b], [tx('t1', '2025-02-11', 10030)], 3);
    expect(result).toHaveLength(1);
    expect(result[0].bills.map(x => x.id)).toEqual([b.id]);
    expect(result[0].fee).toBe(30);
  });

  it('groups several bills paid in one transaction', () => {
    const a = bill(10000, '2025-02-10');
    const b = bill(5000, '2025-02-10');
    const unrelated = bill(7000, '2025-02-10');
    const result = suggestMatches([a, b, unrelated], [tx('t1', '2025-02-10', 15040)], 3);
    expect(result[0].bills.map(x => x.id).sort()).toEqual([a.id, b.id].sort());
    expect(result[0].fee).toBe(40);
  });

  it('ignores bills outside the date window', () => {
    expect(suggestMatches([bill(10000, '2025-02-01')], [tx('t1', '2025-02-11', 10030)], 3)).toEqual([]);
  });

  it('rejects when the leftover is not a plausible fee', () => {
    expect(suggestMatches([bill(10000, '2025-02-10')], [tx('t1', '2025-02-10', 20000)], 3)).toEqual([]);
    expect(suggestMatches([bill(10000, '2025-02-10')], [tx('t1', '2025-02-10', 9500)], 3)).toEqual([]);
  });

  it('skips paid bills, bills without a file date and already linked transactions', () => {
    const paid = bill(10000, '2025-02-10', { transaction_ids: ['x'] });
    const undated = bill(10000, null);
    expect(suggestMatches([paid, undated], [tx('t1', '2025-02-10', 10030)], 3)).toEqual([]);
    const linked = bill(10000, '2025-02-10', { transaction_ids: ['t1'] });
    expect(suggestMatches([linked, bill(10000, '2025-02-10')], [tx('t1', '2025-02-10', 10030, [linked.id])], 3)).toEqual([]);
  });

  it('leaves transactions marked as accounted for alone', () => {
    const marked = { ...tx('t1', '2025-02-10', 10030), accounted: true };
    expect(suggestMatches([bill(10000, '2025-02-10')], [marked], 3)).toEqual([]);
  });

  it('matches against what is left after the accounted-for amount', () => {
    const b = bill(10000, '2025-02-10');
    const t = { ...tx('t1', '2025-02-10', 30030), accounted_amount: 20000 };
    const result = suggestMatches([b], [t], 3);
    expect(result).toHaveLength(1);
    expect(result[0].fee).toBe(30);
  });

  it('uses each bill once, for the earlier transaction', () => {
    const b = bill(10000, '2025-02-10');
    const result = suggestMatches([b], [tx('late', '2025-02-12', 10030), tx('early', '2025-02-10', 10030)], 3);
    expect(result.map(s => s.transaction.id)).toEqual(['early']);
  });

  it('treats a group as one payment worth the total of its members', () => {
    // 41,759 + 8,972 transferred the same day to pay one bill of 50,630
    const b = bill(50630, '2025-02-10');
    const members = [
      { ...tx('t1', '2025-02-10', 41759), group_id: 'g' },
      { ...tx('t2', '2025-02-10', 8972), group_id: 'g' },
    ];
    expect(suggestMatches([b], [members[0]], 3)).toEqual([]);
    const result = suggestMatches([b], members, 3);
    expect(result).toHaveLength(1);
    expect(result[0].members.map(m => m.id)).toEqual(['t1', 't2']);
    expect(result[0].fee).toBe(101);
  });

  it('uses the nearest member date for a group', () => {
    const b = bill(10000, '2025-05-13');
    const members = [{ ...tx('t1', '2025-04-29', 3000), group_id: 'g' }, { ...tx('t2', '2025-05-13', 7030), group_id: 'g' }];
    expect(suggestMatches([b], members, 0)).toHaveLength(1);
  });

  describe('late bills', () => {
    it('allows the payment to come days after the file date, but only a little before it', () => {
      const b = bill(10000, '2025-02-01');
      expect(suggestMatches([b], [tx('t1', '2025-02-20', 10030)], { before: 3, after: 30 })).toHaveLength(1);
      expect(suggestMatches([b], [tx('t1', '2025-02-20', 10030)], { before: 3, after: 10 })).toEqual([]);
      expect(suggestMatches([b], [tx('t1', '2025-01-20', 10030)], { before: 3, after: 30 })).toEqual([]);
      expect(suggestMatches([b], [tx('t1', '2025-01-29', 10030)], { before: 3, after: 30 })).toHaveLength(1);
    });

    it('finds the late bill as a second transaction on a day that already has the regular payment', () => {
      const regular = bill(10000, '2025-02-10');
      const late = bill(4000, '2025-02-10', { period_start: '2024-11-01', period_end: '2024-11-30' });
      const result = suggestMatches([regular, late], [tx('t1', '2025-02-10', 10030), tx('t2', '2025-02-10', 4010)], 3);
      expect(result).toHaveLength(2);
      expect(result.find(s => s.transaction.id === 't1')?.bills.map(b => b.id)).toEqual([regular.id]);
      expect(result.find(s => s.transaction.id === 't2')?.bills.map(b => b.id)).toEqual([late.id]);
    });

    it('rescans a payment that has bills linked but a large remainder, and tops it up', () => {
      const linked = bill(10000, '2025-02-10', { transaction_ids: ['t1'] });
      const missed = bill(4000, '2025-02-10', { period_start: '2024-11-01' });
      const t = tx('t1', '2025-02-10', 14030, [linked.id]);
      const result = suggestMatches([linked, missed], [t], 3);
      expect(result).toHaveLength(1);
      expect(result[0].bills.map(b => b.id)).toEqual([missed.id]);
      expect(result[0].alreadyLinked.map(b => b.id)).toEqual([linked.id]);
      expect(result[0].fee).toBe(30);
    });

    it('leaves a fully explained payment alone', () => {
      const linked = bill(10000, '2025-02-10', { transaction_ids: ['t1'] });
      expect(suggestMatches([linked, bill(30, '2025-02-10')], [tx('t1', '2025-02-10', 10030, [linked.id])], 3)).toEqual([]);
    });
  });

  it('accepts bills that exceed the payment by rounding, but not by more', () => {
    const b = bill(10000, '2025-02-10');
    const within = suggestMatches([b], [tx('t1', '2025-02-10', 9850)], 3);
    expect(within).toHaveLength(1);
    expect(within[0].fee).toBe(0);
    expect(suggestMatches([b], [tx('t1', '2025-02-10', 9790)], 3)).toEqual([]);
  });

  it('compares a transfer with what remains of a bill after a credit', () => {
    // 32,186 Ft bill, 23,567 Ft of it already settled by credit: 8,619 Ft left to transfer, plus a 30 Ft fee
    const credited = bill(32186, '2025-05-13', { credit_applied: 23567 });
    const result = suggestMatches([credited], [tx('t1', '2025-05-13', 8649)], 3);
    expect(result).toHaveLength(1);
    expect(result[0].fee).toBe(30);
    // The same transfer says nothing about the bill when there is no credit
    expect(suggestMatches([bill(32186, '2025-05-13')], [tx('t1', '2025-05-13', 8649)], 3)).toEqual([]);
  });
});
