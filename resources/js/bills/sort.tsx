import { readParam } from './urlState';
import { t } from './format';
import type { BankTransaction, Bill } from './types';

export type Order = 'asc' | 'desc';

/** Newest first unless the URL says otherwise. */
export const readOrder = (param: string): Order => (readParam(param) === 'asc' ? 'asc' : 'desc');

const cmp = (a: string | number, b: string | number): number => {
  if (a < b) return -1;
  return a > b ? 1 : 0;
};

/** Accounting period start, then end, then amount and id so equal periods always keep the same order. */
export const compareBills = (a: Bill, b: Bill): number => cmp(a.period_start, b.period_start)
  || cmp(a.period_end, b.period_end)
  || cmp(a.amount, b.amount)
  || cmp(a.id, b.id);

/** Paid-on date, then amount and id. */
export const compareTransactions = (a: BankTransaction, b: BankTransaction): number => cmp(a.date, b.date)
  || cmp(a.amount, b.amount)
  || cmp(a.id, b.id);

export const applyOrder = (order: Order, result: number): number => (order === 'asc' ? result : -result);

interface SortHeaderProps {
  label: string;
  order: Order;
  onToggle: () => void;
}

/** Column header that flips the sort direction of its table when clicked. */
export function SortHeader({ label, order, onToggle }: SortHeaderProps) {
  return (
    <th aria-sort={order === 'asc' ? 'ascending' : 'descending'}>
      <button type="button" className="btn btn-link p-0 text-reset fw-bold text-decoration-none" title={t('sort-toggle')} onClick={onToggle}>
        {label}
        {order === 'asc' ? ' ▲' : ' ▼'}
      </button>
    </th>
  );
}
