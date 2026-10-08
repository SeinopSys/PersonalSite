import { maxFeeFor, ROUNDING_TOLERANCE } from './matching';
import type { BankTransaction, Bill } from './types';

export interface Reconciliation {
  /** What the transfer cost on top of what is explained, when the remainder is small enough to be a fee */
  fee: number;
  /** The fee as a percentage of what was actually transferred (amount without the fee), or null without a fee */
  feePercent: number | null;
  /** Money that left the account but nothing explains: the whole amount when nothing is linked or entered */
  unaccounted: number;
  /** How far the linked bills and the accounted-for amount exceed the transaction amount, which shouldn't happen */
  excess: number;
}

/** Fee as a percentage of the amount transferred before the fee was added. */
export const feePercentOf = (amount: number, fee: number): number | null => (fee > 0 && amount > fee ? (fee / (amount - fee)) * 100 : null);

/**
 * Splits a payment into what is explained (linked bills plus any manually accounted-for amount),
 * a plausible transfer fee, and the rest.
 */
export function reconcile(amount: number, billsTotal: number, billCount: number, accountedAmount = 0): Reconciliation {
  if (billCount === 0 && accountedAmount === 0) return {
    fee: 0, feePercent: null, unaccounted: amount, excess: 0,
  };
  const remainder = amount - billsTotal - accountedAmount;
  if (remainder < 0) return {
    fee: 0, feePercent: null, unaccounted: 0, excess: -remainder > ROUNDING_TOLERANCE ? -remainder : 0,
  };
  if (remainder <= maxFeeFor(amount)) return {
    fee: remainder, feePercent: feePercentOf(amount, remainder), unaccounted: 0, excess: 0,
  };
  return {
    fee: 0, feePercent: null, unaccounted: remainder, excess: 0,
  };
}

/** The members of a transaction's group, or just the transaction when it isn't grouped. */
export const membersOf = (tx: BankTransaction, transactions: BankTransaction[]): BankTransaction[] => (
  tx.group_id ? transactions.filter(other => other.group_id === tx.group_id) : [tx]
);

/**
 * Reconciles a group as one payment: the members' total against every distinct bill linked to any member.
 * A group counts as accounted for when any member is marked so.
 */
export function reconcileGroup(members: BankTransaction[], billById: Map<string, Bill>): Reconciliation & { skipped: boolean } {
  const billIds = Array.from(new Set(members.flatMap(m => m.bill_ids)));
  const result = reconcile(
    members.reduce((acc, m) => acc + m.amount, 0),
    billIds.reduce((acc, id) => acc + (billById.get(id)?.amount ?? 0), 0),
    billIds.length,
    members.reduce((acc, m) => acc + (m.accounted_amount ?? 0), 0),
  );
  return { ...result, skipped: members.some(m => m.accounted) };
}
