/* eslint-disable import/no-extraneous-dependencies */
import { describe, expect, it } from 'vitest';
import { feePercentOf, reconcile, reconcileGroup } from './reconcile';
import type { BankTransaction, Bill } from './types';

describe('reconcile', () => {
  it('treats a transaction without bills as entirely unaccounted for', () => {
    expect(reconcile(54460, 0, 0)).toMatchObject({ fee: 0, unaccounted: 54460, excess: 0 });
  });

  it('reports a small remainder as the transfer fee', () => {
    expect(reconcile(10030, 10000, 1)).toMatchObject({ fee: 30, unaccounted: 0, excess: 0 });
    expect(reconcile(10000, 10000, 1)).toMatchObject({ fee: 0, unaccounted: 0, excess: 0 });
  });

  it('reports a remainder too large to be a fee as unaccounted for', () => {
    expect(reconcile(20645, 6574, 2)).toMatchObject({ fee: 0, unaccounted: 14071, excess: 0 });
  });

  it('deducts a manually accounted-for amount before working out the fee', () => {
    expect(reconcile(60000, 0, 0, 59800)).toMatchObject({ fee: 200, unaccounted: 0, excess: 0 });
    expect(reconcile(60000, 20000, 1, 39900)).toMatchObject({ fee: 100, unaccounted: 0, excess: 0 });
  });

  it('still reports what neither the bills nor the accounted-for amount explain', () => {
    expect(reconcile(60000, 20000, 1, 10000)).toMatchObject({ fee: 0, unaccounted: 30000, excess: 0 });
    expect(reconcile(60000, 0, 0, 10000)).toMatchObject({ fee: 0, unaccounted: 50000, excess: 0 });
  });

  it('flags bills that add up to more than the transaction', () => {
    expect(reconcile(8649, 32186, 1)).toMatchObject({ fee: 0, unaccounted: 0, excess: 23537 });
  });
});

describe('rounding tolerance', () => {
  it('ignores bills exceeding the payment by about 200 Ft or less', () => {
    expect(reconcile(20645, 20838, 4)).toMatchObject({ fee: 0, unaccounted: 0, excess: 0 });
    expect(reconcile(20645, 20845, 4)).toMatchObject({ excess: 0 });
  });

  it('still flags a bigger excess', () => {
    expect(reconcile(20645, 20846, 4)).toMatchObject({ excess: 201 });
  });
});

describe('feePercentOf', () => {
  it('is relative to the amount transferred before the fee', () => {
    expect(feePercentOf(34634, 121)).toBeCloseTo(0.3506, 3);
    expect(feePercentOf(1000, 0)).toBeNull();
  });

  it('is part of the result when the remainder is a fee', () => {
    expect(reconcile(10030, 10000, 1).feePercent).toBeCloseTo(0.3, 2);
    expect(reconcile(10000, 0, 0).feePercent).toBeNull();
  });
});

const bill = (id: string, amount: number): Bill => ({
  id, type: 'water', sha256: null, invoice_number: null, period_start: '2025-01-01', period_end: '2025-01-31', due_date: null, file_modified_at: null, advance: false, credit_applied: 0, credit_source_id: null, amount, transaction_ids: [],
});
const member = (id: string, amount: number, billIds: string[], extra: Partial<BankTransaction> = {}): BankTransaction => ({
  id, date: '2025-04-29', amount, note: null, accounted: false, accounted_amount: null, group_id: 'g', bill_ids: billIds, ...extra,
});

describe('reconcileGroup', () => {
  const billById = new Map([bill('heat', 20556), bill('elec', 32186)].map(b => [b.id, b]));

  it('compares the members total with the distinct bills linked to any member', () => {
    // 44,277 + 8,649 paid heat 20,556 + elec 32,186; elec is linked to both members
    const result = reconcileGroup([member('a', 44277, ['heat', 'elec']), member('b', 8649, ['elec'])], billById);
    expect(result).toMatchObject({
      fee: 184, unaccounted: 0, excess: 0, skipped: false,
    });
    expect(result.feePercent).toBeCloseTo(0.35, 2);
  });

  it('works when the bills are linked to just one member', () => {
    expect(reconcileGroup([member('a', 44277, ['heat', 'elec']), member('b', 8649, [])], billById)).toMatchObject({ fee: 184 });
  });

  it('reports the rest as unaccounted and skips groups with an accounted-for member', () => {
    expect(reconcileGroup([member('a', 44277, ['heat']), member('b', 8649, [])], billById)).toMatchObject({ unaccounted: 32370 });
    expect(reconcileGroup([member('a', 100, [], { accounted: true }), member('b', 50, [])], billById).skipped).toBe(true);
  });
});

describe('credit applied', () => {
  const credited = bill('elec', 32186);
  credited.credit_applied = 23567;
  const byId = new Map([[credited.id, credited]]);

  it('counts only what had to be transferred when checking a payment', () => {
    const result = reconcileGroup([member('p', 8649, ['elec'], { group_id: null })], byId);
    expect(result).toMatchObject({ fee: 30, unaccounted: 0, excess: 0 });
    expect(result.feePercent).toBeCloseTo(0.348, 2);
  });

  it('is not enough on its own without the credit', () => {
    const plain = bill('elec', 32186);
    expect(reconcileGroup([member('p', 8649, ['elec'], { group_id: null })], new Map([[plain.id, plain]]))).toMatchObject({ excess: 23537 });
  });
});
