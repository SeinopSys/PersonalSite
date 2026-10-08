import type { Bill, DraftBill } from './types';

export type FoundBy = 'sha256' | 'invoice_number' | 'period_amount';

/**
 * Looks a bill up by the same signals the server uses to spot duplicates: the same file, the same invoice number,
 * or the same period and amount. The type isn't known for a file picked just to be looked up, so it is ignored.
 */
export function findExisting(draft: DraftBill, bills: Bill[]): { bill: Bill; by: FoundBy } | null {
  if (draft.sha256) {
    const bill = bills.find(b => b.sha256 === draft.sha256);
    if (bill) return { bill, by: 'sha256' };
  }
  const invoice = draft.invoiceNumber.trim();
  if (invoice) {
    const bill = bills.find(b => b.invoice_number === invoice);
    if (bill) return { bill, by: 'invoice_number' };
  }
  const amount = draft.amount.trim();
  if (draft.periodStart && draft.periodEnd && /^-?\d+$/.test(amount)) {
    const bill = bills.find(b => b.period_start === draft.periodStart && b.period_end === draft.periodEnd && String(b.amount) === amount);
    if (bill) return { bill, by: 'period_amount' };
  }
  return null;
}
