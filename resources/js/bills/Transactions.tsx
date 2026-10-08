import { useEffect, useState } from 'preact/hooks';
import { Pagination } from './Pagination';
import {
  membersOf, reconcile, reconcileGroup,
} from './reconcile';
import {
  applyOrder, compareTransactions, Order, readOrder, SortHeader,
} from './sort';
import { readPage, writeParams } from './urlState';
import { Suggestions } from './Suggestions';
import { BankTransaction, Bill } from './types';
import {
  formatDate, formatMoney, formatPercent, formatPeriod, t, todayIso,
} from './format';

/** The remainder of a transaction after its linked bills, worded and coloured by what it is. */
function Outcome({ result }: { result: ReturnType<typeof reconcile> }) {
  if (result.unaccounted > 0) {
    return <span className="text-danger-emphasis fw-semibold">{`${t('unaccounted')}: ${formatMoney(result.unaccounted)}`}</span>;
  }
  if (result.excess > 0) {
    return <span className="text-warning-emphasis">{`${t('fee-negative')}: ${formatMoney(result.excess)}`}</span>;
  }
  const percent = result.feePercent === null ? '' : ` (${formatPercent(result.feePercent)})`;
  return <span className="text-body-secondary">{`${t('transfer-fee')}: ${formatMoney(result.fee)}${percent}`}</span>;
}

interface TransactionFormProps {
  transaction: BankTransaction | null;
  bills: Bill[];
  onSave: (payload: { date: string; amount: number; note: string | null; accounted: boolean; accounted_amount: number | null; bill_ids: string[] }, id: string | null) => Promise<boolean>;
  onCancel: () => void;
}

function TransactionForm({
  transaction, bills, onSave, onCancel,
}: TransactionFormProps) {
  const [date, setDate] = useState(transaction?.date ?? todayIso());
  const [amount, setAmount] = useState(transaction ? String(transaction.amount) : '');
  const [note, setNote] = useState(transaction?.note ?? '');
  const [selected, setSelected] = useState<string[]>(transaction?.bill_ids ?? []);
  const [accounted, setAccounted] = useState(transaction?.accounted ?? false);
  const [accountedAmount, setAccountedAmount] = useState(transaction?.accounted_amount ? String(transaction.accounted_amount) : '');

  // Unpaid bills, plus whatever this transaction already pays for
  const candidates = bills
    .filter(b => b.transaction_ids.length === 0 || selected.includes(b.id) || transaction?.bill_ids.includes(b.id))
    .sort((a, b) => a.period_start.localeCompare(b.period_start));
  const amountValid = /^-?\d+$/.test(amount.trim());
  const sum = bills.filter(b => selected.includes(b.id)).reduce((acc, b) => acc + b.amount, 0);
  const accountedAmountValid = accountedAmount.trim() === '' || /^\d+$/.test(accountedAmount.trim());
  const accountedValue = accountedAmount.trim() === '' || !accountedAmountValid ? 0 : Number(accountedAmount.trim());
  const result = amountValid ? reconcile(Number(amount.trim()), sum, selected.length, accountedValue) : null;

  return (
    <form
      className="card card-body mb-3"
      onSubmit={async e => {
        e.preventDefault();
        if (!amountValid || !date || !accountedAmountValid) return;
        if (await onSave({
          date, amount: Number(amount.trim()), note: note.trim() || null, accounted, accounted_amount: accountedValue || null, bill_ids: selected,
        }, transaction?.id ?? null)) onCancel();
      }}
    >
      <h4>{transaction ? t('edit-transaction') : t('add-transaction')}</h4>
      <div className="row g-2 mb-2">
        <div className="col-sm-3">
          <label className="form-label" htmlFor="tx-date">{t('paid-on')}</label>
          <input id="tx-date" type="date" required className="form-control" value={date} onChange={e => setDate(e.currentTarget.value)} />
        </div>
        <div className="col-sm-3">
          <label className="form-label" htmlFor="tx-amount">{t('amount')}</label>
          <div className="input-group">
            <input
              id="tx-amount"
              type="text"
              inputMode="numeric"
              required
              className={`form-control${amount && !amountValid ? ' is-invalid' : ''}`}
              value={amount}
              onChange={e => setAmount(e.currentTarget.value)}
            />
            <span className="input-group-text">Ft</span>
          </div>
        </div>
        <div className="col-sm-6">
          <label className="form-label" htmlFor="tx-note">
            {t('note')}
            {' '}
            (
            {window.Laravel.jsLocales.optional ?? ''}
            )
          </label>
          <input id="tx-note" type="text" maxLength={255} className="form-control" value={note} onChange={e => setNote(e.currentTarget.value)} />
        </div>
      </div>
      <div className="mb-2">
        <div className="form-label">{t('linked-bills')}</div>
        {candidates.length === 0 && <div className="text-body-secondary">{t('no-unpaid-bills')}</div>}
        {candidates.map(bill => (
          <div className="form-check" key={bill.id}>
            <input
              className="form-check-input"
              type="checkbox"
              id={`tx-bill-${bill.id}`}
              checked={selected.includes(bill.id)}
              onChange={e => setSelected(e.currentTarget.checked ? [...selected, bill.id] : selected.filter(id => id !== bill.id))}
            />
            <label className="form-check-label" htmlFor={`tx-bill-${bill.id}`}>
              {t(`type-${bill.type}`)}
              ,
              {formatPeriod(bill)}
              :
              {formatMoney(bill.amount)}
            </label>
          </div>
        ))}
        {result && !accounted && (
          <div className="mt-1 small">
            <Outcome result={result} />
          </div>
        )}
      </div>
      <div className="row g-2 mb-2">
        <div className="col-sm-4">
          <label className="form-label" htmlFor="tx-accounted-amount">{t('accounted-amount')}</label>
          <div className="input-group">
            <input
              id="tx-accounted-amount"
              type="text"
              inputMode="numeric"
              className={`form-control${accountedAmountValid ? '' : ' is-invalid'}`}
              value={accountedAmount}
              onChange={e => setAccountedAmount(e.currentTarget.value)}
            />
            <span className="input-group-text">Ft</span>
          </div>
          <div className="form-text">{t('accounted-amount-help')}</div>
        </div>
      </div>
      <div className="form-check mb-3">
        <input
          className="form-check-input"
          type="checkbox"
          id="tx-accounted"
          checked={accounted}
          onChange={e => setAccounted(e.currentTarget.checked)}
        />
        <label className="form-check-label" htmlFor="tx-accounted">{t('mark-accounted')}</label>
        <div className="form-text">{t('mark-accounted-help')}</div>
      </div>
      <div className="d-flex gap-2">
        <button type="submit" className="btn btn-primary" disabled={!amountValid || !date || !accountedAmountValid}>{window.Laravel.jsLocales.save}</button>
        <button type="button" className="btn btn-secondary" onClick={onCancel}>{window.Laravel.jsLocales.cancel}</button>
      </div>
    </form>
  );
}

interface TransactionsProps {
  transactions: BankTransaction[];
  bills: Bill[];
  onSave: TransactionFormProps['onSave'];
  onDelete: (id: string) => void;
  onGroup: (ids: string[]) => Promise<void>;
  onUngroup: (ids: string[]) => Promise<void>;
}

const PAGE_SIZE = 10;

export function Transactions({
  transactions, bills, onSave, onDelete, onGroup, onUngroup,
}: TransactionsProps) {
  // undefined = closed, null = creating, otherwise editing that transaction
  const [form, setForm] = useState<BankTransaction | null | undefined>(undefined);
  const [requestedPage, setRequestedPage] = useState(() => readPage('txpage'));
  const [order, setOrder] = useState<Order>(() => readOrder('txorder'));
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const billById = new Map(bills.map(b => [b.id, b]));
  // A grouped transaction is judged together with the rest of its group, a lone one on its own
  const outcomeOf = (tx: BankTransaction) => reconcileGroup(membersOf(tx, transactions), billById);
  const units = new Map<string, BankTransaction[]>();
  transactions.forEach(tx => {
    const key = tx.group_id ?? tx.id;
    units.set(key, [...(units.get(key) ?? []), tx]);
  });
  const unaccounted = Array.from(units.values()).map(members => reconcileGroup(members, billById)).filter(r => !r.skipped && r.unaccounted > 0);
  const unaccountedTotal = unaccounted.reduce((acc, r) => acc + r.unaccounted, 0);
  const selectedTxs = transactions.filter(tx => selectedIds.includes(tx.id));
  const toggleSelected = (id: string) => setSelectedIds(current => (current.includes(id) ? current.filter(x => x !== id) : [...current, id]));
  // A group is one row, listed by its earliest transaction; its members are stacked inside the row
  const rows = Array.from(units.values())
    .map(members => [...members].sort(compareTransactions))
    .sort((a, b) => applyOrder(order, compareTransactions(a[0], b[0])));
  const pageCount = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
  const page = Math.min(requestedPage, pageCount);
  const visible = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  useEffect(() => {
    writeParams({ txpage: page === 1 ? null : page, txorder: order === 'desc' ? null : order });
  }, [page, order]);

  return (
    <section className="mb-4">
      <div className="d-flex justify-content-between align-items-center mb-2">
        <h3 className="mb-0">{t('transactions')}</h3>
        {form === undefined && <button type="button" className="btn btn-primary" onClick={() => setForm(null)}>{t('add-transaction')}</button>}
      </div>
      <Suggestions bills={bills} transactions={transactions} order={order} onApply={onSave} />
      {form !== undefined && (
        <TransactionForm
          key={form?.id ?? 'new'}
          transaction={form}
          bills={bills}
          onSave={onSave}
          onCancel={() => setForm(undefined)}
        />
      )}
      {unaccounted.length > 0 && (
        <p className="text-danger-emphasis">
          {t('unaccounted-summary', { count: unaccounted.length, total: formatMoney(unaccountedTotal) })}
        </p>
      )}
      {transactions.length === 0 ? <p className="text-body-secondary">{t('no-transactions')}</p> : (
        <>
          {selectedTxs.length > 0 && (
            <div className="d-flex gap-2 align-items-center flex-wrap mb-2">
              <span className="text-body-secondary">{t('selected-count', { count: selectedTxs.length })}</span>
              {selectedTxs.length >= 2 && (
                <button type="button" className="btn btn-sm btn-primary" onClick={async () => { await onGroup(selectedTxs.map(tx => tx.id)); setSelectedIds([]); }}>
                  {t('group-selected')}
                </button>
              )}
              {selectedTxs.some(tx => tx.group_id) && (
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={async () => { await onUngroup(selectedTxs.map(tx => tx.id)); setSelectedIds([]); }}>
                  {t('ungroup-selected')}
                </button>
              )}
              <button type="button" className="btn btn-sm btn-link" onClick={() => setSelectedIds([])}>{t('clear-selection')}</button>
            </div>
          )}
          <div className="table-responsive">
            <table className="table table-bordered align-middle">
              <thead>
                <tr>
                  <th aria-label={t('select-transaction')} />
                  <SortHeader label={t('paid-on')} order={order} onToggle={() => { setOrder(order === 'asc' ? 'desc' : 'asc'); setRequestedPage(1); }} />
                  <th>{t('amount')}</th>
                  <th>{t('bills-and-note')}</th>
                  <th>{t('fee-or-unaccounted')}</th>
                  <th>{t('actions')}</th>
                </tr>
              </thead>
              <tbody>
                {visible.map(members => {
                  const grouped = members.length > 1;
                  const outcome = outcomeOf(members[0]);
                  // Bills are shared by the group, so they are listed once however many members link them
                  const rowBills = Array.from(new Set(members.flatMap(m => m.bill_ids)))
                    .map(id => billById.get(id))
                    .filter((b): b is Bill => !!b)
                    .sort((x, y) => x.period_start.localeCompare(y.period_start));
                  const otherAccounted = members.reduce((acc, m) => acc + (m.accounted_amount ?? 0), 0);
                  return (
                    <tr key={members[0].id}>
                      <td className="bills-stack">
                        {members.map(m => (
                          <div key={m.id}>
                            <input
                              type="checkbox"
                              className="form-check-input"
                              aria-label={t('select-transaction')}
                              checked={selectedIds.includes(m.id)}
                              onChange={() => toggleSelected(m.id)}
                            />
                          </div>
                        ))}
                      </td>
                      <td className="bills-stack">
                        {members.map(m => <div key={m.id}>{formatDate(m.date)}</div>)}
                        {grouped && <div className="small text-body-secondary">{t('group-label', { count: members.length })}</div>}
                      </td>
                      <td className="bills-stack text-end">
                        {members.map(m => <div key={m.id} className="justify-content-end">{formatMoney(m.amount)}</div>)}
                        {grouped && (
                          <div className="justify-content-end fw-semibold border-top">
                            {formatMoney(members.reduce((acc, m) => acc + m.amount, 0))}
                          </div>
                        )}
                      </td>
                      <td>
                        {rowBills.map(b => (
                          <div key={b.id}>
                            {t(`type-${b.type}`)}
                            ,
                            {' '}
                            {formatPeriod(b)}
                          </div>
                        ))}
                        {otherAccounted > 0 && <div className="text-body-secondary">{t('other-accounted', { amount: formatMoney(otherAccounted) })}</div>}
                        {members.filter(m => m.note).map(m => (
                          <div key={m.id} className="text-body-secondary fst-italic">
                            {grouped ? `${formatDate(m.date)}: ${m.note}` : m.note}
                          </div>
                        ))}
                      </td>
                      <td className="text-end">
                        {outcome.skipped ? <span className="text-body-secondary">{t('known-accounted')}</span> : (
                          <>
                            {grouped && <div className="small text-body-secondary">{t('group-total')}</div>}
                            <Outcome result={outcome} />
                          </>
                        )}
                      </td>
                      <td className="bills-stack text-nowrap">
                        {members.map(m => (
                          <div key={m.id}>
                            <button type="button" className="btn btn-sm btn-secondary me-1" onClick={() => setForm(m)}>{t('edit')}</button>
                            <button type="button" className="btn btn-sm btn-danger" onClick={() => onDelete(m.id)}>{t('delete')}</button>
                          </div>
                        ))}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination page={page} pages={pageCount} onPage={setRequestedPage} />
        </>
      )}
    </section>
  );
}
