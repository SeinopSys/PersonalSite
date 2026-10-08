import type { BankTransaction, Bill } from './types';

export interface Suggestion {
  /** The transaction the bills get linked to: the earliest member when the payment is a group */
  transaction: BankTransaction;
  /** Every transaction making up the payment (just the one unless it is grouped) */
  members: BankTransaction[];
  /** The bills suggested on top of whatever the payment already has linked */
  bills: Bill[];
  /** Bills the payment already had linked, explaining only part of it */
  alreadyLinked: Bill[];
  /** What the transfer cost on top of the bills */
  fee: number;
}

/**
 * How far a payment may be from a bill file's date, in days. A bill that was missed and requested later gets its
 * file saved first and paid on that day or after, so the span after the file date is the longer one.
 */
export interface MatchWindow {
  /** Payment up to this many days before the file date */
  before: number;
  /** Payment up to this many days after the file date */
  after: number;
}

const DAY = 86400000;
const MAX_CANDIDATES = 14;

const dayNumber = (iso: string): number => {
  const [y, m, d] = iso.split('-').map(Number);
  return Math.round(Date.UTC(y, m - 1, d) / DAY);
};

/** Differences up to this many Ft, either way, are treated as rounding (credits, rounded-up payments) and not worth flagging. */
export const ROUNDING_TOLERANCE = 200;

/** What had to be transferred for a bill: its amount less any credit carried over from an earlier overpayment. */
export const payableAmount = (bill: Bill): number => bill.amount - (bill.credit_applied ?? 0);

/** Transfer fees seen so far are around 0.2-0.4% with a small minimum; allow some slack above that. */
export const maxFeeFor = (amount: number): number => Math.max(500, Math.round(amount * 0.02));

interface Payment {
  members: BankTransaction[];
  amount: number;
  accountedAmount: number;
  linked: Bill[];
}

/**
 * Ungrouped transactions are payments on their own; a group is one payment whose amount is the members' total.
 * Payments that already have bills linked are kept only while a remainder bigger than a fee is still unexplained,
 * so they can be topped up with further bills.
 */
function paymentsOf(transactions: BankTransaction[], billById: Map<string, Bill>): Payment[] {
  const byGroup = new Map<string, BankTransaction[]>();
  const singles: BankTransaction[][] = [];
  transactions.forEach(tx => {
    if (!tx.group_id) singles.push([tx]);
    else byGroup.set(tx.group_id, [...(byGroup.get(tx.group_id) ?? []), tx]);
  });
  return [...singles, ...Array.from(byGroup.values())]
    .filter(members => members.every(m => !m.accounted))
    .map(members => {
      const linkedIds = Array.from(new Set(members.flatMap(m => m.bill_ids)));
      return {
        members: [...members].sort((a, b) => a.date.localeCompare(b.date)),
        amount: members.reduce((acc, m) => acc + m.amount, 0),
        accountedAmount: members.reduce((acc, m) => acc + (m.accounted_amount ?? 0), 0),
        linked: linkedIds.map(id => billById.get(id)).filter((b): b is Bill => !!b),
      };
    })
    .filter(p => {
      const remainder = p.amount - p.accountedAmount - p.linked.reduce((acc, b) => acc + payableAmount(b), 0);
      return p.linked.length === 0 ? remainder > 0 : remainder > maxFeeFor(p.amount);
    });
}

/**
 * Suggests which unpaid bills each payment covered, based on how close the bill files' modification dates are to
 * the transaction date (any member's date, for a group). A payment qualifies when the bills add up to at most what
 * is still unexplained and the difference is a plausible transfer fee. The smallest, closest set of bills wins.
 * Each bill is used once. Payments with some bills already linked are scanned again for the rest.
 */
export function suggestMatches(bills: Bill[], transactions: BankTransaction[], window: number | MatchWindow): Suggestion[] {
  const { before, after } = typeof window === 'number' ? { before: window, after: window } : window;
  const used = new Set<string>();
  const suggestions: Suggestion[] = [];
  const open = bills.filter(b => b.transaction_ids.length === 0 && b.file_modified_at);
  const payments = paymentsOf(transactions, new Map(bills.map(b => [b.id, b])))
    .sort((a, b) => a.members[0].date.localeCompare(b.members[0].date));

  payments.forEach(payment => {
    const memberDays = payment.members.map(m => dayNumber(m.date));
    // Days from the file date to the closest member payment that is allowed, or null when none is
    const distance = (bill: Bill): number | null => {
      const fileDay = dayNumber(bill.file_modified_at as string);
      const gaps = memberDays.map(d => d - fileDay).filter(delta => delta >= -before && delta <= after);
      return gaps.length ? Math.min(...gaps.map(Math.abs)) : null;
    };
    const candidates = open
      .filter(b => !used.has(b.id))
      .map(bill => ({ bill, distance: distance(bill) }))
      .filter((c): c is { bill: Bill; distance: number } => c.distance !== null)
      .sort((a, b) => a.distance - b.distance)
      .slice(0, MAX_CANDIDATES);
    if (candidates.length === 0) return;

    // Whatever is already explained, by hand or by bills linked earlier, isn't available to new bills
    const available = payment.amount - payment.accountedAmount - payment.linked.reduce((acc, b) => acc + b.amount, 0);
    const feeLimit = maxFeeFor(payment.amount);
    let best: { subset: typeof candidates; fee: number; distance: number } | null = null;
    for (let mask = 1; mask < 2 ** candidates.length; mask += 1) {
      const subset = candidates.filter((_, i) => mask & (2 ** i)); // eslint-disable-line no-bitwise
      const fee = available - subset.reduce((acc, c) => acc + payableAmount(c.bill), 0);
      // Bills adding up to slightly more than the payment still count: that is rounding, not a different payment
      if (fee >= -ROUNDING_TOLERANCE && fee <= feeLimit) {
        const total = subset.reduce((acc, c) => acc + c.distance, 0);
        if (
          !best
          || total < best.distance
          || (total === best.distance && subset.length < best.subset.length)
          || (total === best.distance && subset.length === best.subset.length && fee < best.fee)
        ) {
          best = { subset, fee, distance: total };
        }
      }
    }
    if (best) {
      best.subset.forEach(c => used.add(c.bill.id));
      suggestions.push({
        transaction: payment.members[0],
        members: payment.members,
        bills: best.subset.map(c => c.bill),
        alreadyLinked: payment.linked,
        fee: Math.max(0, best.fee),
      });
    }
  });

  return suggestions;
}
