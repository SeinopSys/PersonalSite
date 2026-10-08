import { useState } from 'preact/hooks';
import { suggestMatches, Suggestion } from './matching';
import { applyOrder, compareTransactions, Order } from './sort';
import {
  formatDate, formatMoney, formatPercent, formatPeriod, t,
} from './format';
import { feePercentOf } from './reconcile';
import { BankTransaction, Bill } from './types';

interface SuggestionsProps {
  order: Order;
  bills: Bill[];
  transactions: BankTransaction[];
  onApply: (payload: { date: string; amount: number; note: string | null; accounted: boolean; accounted_amount: number | null; bill_ids: string[] }, id: string) => Promise<boolean>;
}

/** The fee as a percentage of what was actually transferred, so unusually expensive transfers stand out. */
function FeePercent({ members, fee }: { members: BankTransaction[]; fee: number }) {
  const transferred = members.reduce((acc, m) => acc + m.amount - (m.accounted_amount ?? 0), 0);
  const percent = feePercentOf(transferred, fee);
  return percent === null ? null : <span className="text-body-secondary">{` (${formatPercent(percent)})`}</span>;
}

export function Suggestions({
  bills, transactions, order, onApply,
}: SuggestionsProps) {
  const [daysBefore, setDaysBefore] = useState('3');
  const [daysAfter, setDaysAfter] = useState('30');
  const [found, setFound] = useState<Suggestion[] | null>(null);
  const [rejected, setRejected] = useState<string[]>([]);
  const [applying, setApplying] = useState(false);

  const accepted = (found ?? []).filter(s => !rejected.includes(s.transaction.id));

  const apply = async () => {
    setApplying(true);
    // Sequential on purpose: each save reloads the data the next one depends on
    for (let i = 0; i < accepted.length; i += 1) {
      const { transaction, bills: matched, alreadyLinked } = accepted[i];
      // eslint-disable-next-line no-await-in-loop
      const ok = await onApply({
        date: transaction.date,
        amount: transaction.amount,
        note: transaction.note,
        accounted: transaction.accounted,
        accounted_amount: transaction.accounted_amount,
        // Bills the payment already had stay linked; the suggested ones are added
        bill_ids: [...alreadyLinked.map(b => b.id), ...matched.map(b => b.id)],
      }, transaction.id);
      if (!ok) break;
    }
    setApplying(false);
    setFound(null);
    setRejected([]);
  };

  return (
    <div className="mb-3">
      <div className="d-flex gap-2 align-items-end flex-wrap">
        <div>
          <label className="form-label mb-1" htmlFor="match-before">{t('match-before')}</label>
          <div className="input-group">
            <input id="match-before" type="number" min={0} max={60} className="form-control" value={daysBefore} onChange={e => setDaysBefore(e.currentTarget.value)} />
            <span className="input-group-text">{t('days')}</span>
          </div>
        </div>
        <div>
          <label className="form-label mb-1" htmlFor="match-after">{t('match-after')}</label>
          <div className="input-group">
            <input id="match-after" type="number" min={0} max={365} className="form-control" value={daysAfter} onChange={e => setDaysAfter(e.currentTarget.value)} />
            <span className="input-group-text">{t('days')}</span>
          </div>
        </div>
        <button
          type="button"
          className="btn btn-outline-primary"
          onClick={() => {
            setRejected([]);
            setFound(suggestMatches(bills, transactions, {
              before: Math.max(0, Number(daysBefore) || 0),
              after: Math.max(0, Number(daysAfter) || 0),
            }));
          }}
        >
          {t('suggest-matches')}
        </button>
      </div>

      {found && (found.length === 0 ? <p className="mt-2 text-body-secondary">{t('no-suggestions')}</p> : (
        <div className="mt-3">
          <div className="table-responsive">
            <table className="table table-bordered align-middle">
              <thead>
                <tr>
                  <th aria-label={t('accept')} />
                  <th>{t('paid-on')}</th>
                  <th>{t('amount')}</th>
                  <th>{t('linked-bills')}</th>
                  <th>{t('transfer-fee')}</th>
                </tr>
              </thead>
              <tbody>
                {[...found].sort((a, b) => applyOrder(order, compareTransactions(a.transaction, b.transaction))).map(({
                  transaction, members, bills: matched, alreadyLinked, fee,
                }) => (
                  <tr key={transaction.id} className={rejected.includes(transaction.id) ? 'text-body-secondary' : undefined}>
                    <td>
                      <input
                        type="checkbox"
                        className="form-check-input"
                        aria-label={t('accept')}
                        checked={!rejected.includes(transaction.id)}
                        onChange={e => setRejected(e.currentTarget.checked
                          ? rejected.filter(id => id !== transaction.id)
                          : [...rejected, transaction.id])}
                      />
                    </td>
                    <td>{members.map(m => formatDate(m.date)).join(', ')}</td>
                    <td className="text-end">{formatMoney(members.reduce((acc, m) => acc + m.amount, 0))}</td>
                    <td>
                      {alreadyLinked.length > 0 && <div className="text-body-secondary">{t('already-linked', { count: alreadyLinked.length })}</div>}
                      {matched.map(b => (
                        <div key={b.id}>
                          {t(`type-${b.type}`)}
                          {', '}
                          {formatPeriod(b)}
                          {': '}
                          {formatMoney(b.amount)}
                          {b.file_modified_at && <span className="small text-body-secondary">{` (${formatDate(b.file_modified_at)})`}</span>}
                        </div>
                      ))}
                    </td>
                    <td className="text-end">
                      {formatMoney(fee)}
                      <FeePercent members={members} fee={fee} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <button type="button" className="btn btn-primary" disabled={applying || accepted.length === 0} onClick={apply}>
            {t('apply-matches')}
            {` (${accepted.length})`}
          </button>
        </div>
      ))}
    </div>
  );
}
