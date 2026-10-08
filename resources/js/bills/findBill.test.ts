/* eslint-disable import/no-extraneous-dependencies */
import { describe, expect, it } from 'vitest';
import { findExisting } from './findBill';
import type { Bill, DraftBill } from './types';

const bill = (id: string, extra: Partial<Bill> = {}): Bill => ({
  id,
  type: 'water',
  sha256: null,
  invoice_number: null,
  period_start: '2025-01-01',
  period_end: '2025-01-31',
  due_date: null,
  file_modified_at: null,
  advance: false,
  amount: 5000,
  transaction_ids: [],
  ...extra,
});

const draft = (extra: Partial<DraftBill> = {}): DraftBill => ({
  key: 1,
  fileName: 'x.pdf',
  type: 'electricity',
  sha256: null,
  invoiceNumber: '',
  periodStart: '',
  periodEnd: '',
  dueDate: '',
  fileModified: '',
  advance: false,
  amount: '',
  ...extra,
});

describe('findExisting', () => {
  it('finds a bill by file hash first', () => {
    const a = bill('a', { sha256: 'abc' });
    const b = bill('b', { invoice_number: 'INV/1' });
    expect(findExisting(draft({ sha256: 'abc', invoiceNumber: 'INV/1' }), [b, a])).toEqual({ bill: a, by: 'sha256' });
  });

  it('falls back to the invoice number', () => {
    const b = bill('b', { invoice_number: 'INV/1' });
    expect(findExisting(draft({ sha256: 'other', invoiceNumber: ' INV/1 ' }), [b])).toEqual({ bill: b, by: 'invoice_number' });
  });

  it('then to period and amount, whatever the type', () => {
    const b = bill('b');
    expect(findExisting(draft({ periodStart: '2025-01-01', periodEnd: '2025-01-31', amount: '5000' }), [b])).toEqual({ bill: b, by: 'period_amount' });
  });

  it('reports nothing for an unknown bill or an incomplete one', () => {
    const b = bill('b', { sha256: 'abc' });
    expect(findExisting(draft({ sha256: 'zzz' }), [b])).toBeNull();
    expect(findExisting(draft({ periodStart: '2025-01-01', periodEnd: '2025-01-31' }), [b])).toBeNull();
    expect(findExisting(draft(), [b])).toBeNull();
  });
});
