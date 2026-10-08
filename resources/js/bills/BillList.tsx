import { useEffect, useState } from 'preact/hooks';
import classNames from 'classnames';
import { Pagination } from './Pagination';
import {
  applyOrder, compareBills, Order, readOrder, SortHeader,
} from './sort';
import { readPage, readParam, writeParams } from './urlState';
import {
  Bill, BILL_TYPES, BillType, PeriodAnalysis,
} from './types';
import {
  formatDate, formatMoney, formatPeriod, t, todayIso,
} from './format';

interface BillListProps {
  bills: Bill[];
  analysis: Record<BillType, PeriodAnalysis>;
  onUpdate: (id: string, bill: Bill) => Promise<boolean>;
  onDelete: (id: string) => void;
}

function statusOf(bill: Bill): 'paid' | 'overdue' | 'unpaid' {
  if (bill.transaction_ids.length > 0) return 'paid';
  return bill.due_date && bill.due_date < todayIso() ? 'overdue' : 'unpaid';
}

const STATUS_CLASS = { paid: 'bg-success', overdue: 'bg-danger', unpaid: 'bg-warning text-dark' };

function EditRow({ bill, onSave, onCancel }: { bill: Bill; onSave: (b: Bill) => void; onCancel: () => void }) {
  const [draft, setDraft] = useState({
    start: bill.period_start, end: bill.period_end, due: bill.due_date ?? '', amount: String(bill.amount), invoice: bill.invoice_number ?? '', fileDate: bill.file_modified_at ?? '', advance: bill.advance,
  });
  const valid = draft.start && draft.end && draft.end >= draft.start && /^-?\d+$/.test(draft.amount.trim());

  return (
    <tr>
      <td>
        <div className="d-flex gap-1">
          <input type="date" className="form-control form-control-sm" aria-label={t('period')} value={draft.start} onChange={e => setDraft({ ...draft, start: e.currentTarget.value })} />
          <input type="date" className="form-control form-control-sm" aria-label={t('period')} value={draft.end} onChange={e => setDraft({ ...draft, end: e.currentTarget.value })} />
        </div>
      </td>
      <td>
        <input type="text" inputMode="numeric" className="form-control form-control-sm" aria-label={t('amount')} value={draft.amount} onChange={e => setDraft({ ...draft, amount: e.currentTarget.value })} />
      </td>
      <td><input type="date" className="form-control form-control-sm" aria-label={t('due-date')} value={draft.due} onChange={e => setDraft({ ...draft, due: e.currentTarget.value })} /></td>
      <td><input type="text" className="form-control form-control-sm" aria-label={t('invoice-number')} value={draft.invoice} onChange={e => setDraft({ ...draft, invoice: e.currentTarget.value })} /></td>
      <td><input type="date" className="form-control form-control-sm" aria-label={t('file-date')} value={draft.fileDate} onChange={e => setDraft({ ...draft, fileDate: e.currentTarget.value })} /></td>
      <td>
        <div className="form-check">
          <input
            className="form-check-input"
            type="checkbox"
            id={`edit-advance-${bill.id}`}
            checked={draft.advance}
            onChange={e => setDraft({ ...draft, advance: e.currentTarget.checked })}
          />
          <label className="form-check-label small" htmlFor={`edit-advance-${bill.id}`}>{t('advance-invoice')}</label>
        </div>
      </td>
      <td className="text-nowrap">
        <button
          type="button"
          className="btn btn-sm btn-primary me-1"
          disabled={!valid}
          onClick={() => onSave({
            ...bill,
            period_start: draft.start,
            period_end: draft.end,
            due_date: draft.due || null,
            file_modified_at: draft.fileDate || null,
            advance: draft.advance,
            amount: Number(draft.amount.trim()),
            invoice_number: draft.invoice.trim() || null,
          })}
        >
          {window.Laravel.jsLocales.save}
        </button>
        <button type="button" className="btn btn-sm btn-secondary" onClick={onCancel}>{window.Laravel.jsLocales.cancel}</button>
      </td>
    </tr>
  );
}

const PAGE_SIZE = 10;

export function BillList({
  bills, analysis, onUpdate, onDelete,
}: BillListProps) {
  const initialType = BILL_TYPES.find(type => type === readParam('tab')) ?? BILL_TYPES[0];
  const [activeType, setActiveType] = useState<BillType>(initialType);
  // Only the open tab's page lives in the URL, so other tabs start at page 1 after a reload
  const [pages, setPages] = useState<Partial<Record<BillType, number>>>({ [initialType]: readPage('page') });
  const [editing, setEditing] = useState<string | null>(null);
  const [order, setOrder] = useState<Order>(() => readOrder('order'));

  // Newest first by default, so the bills you're most likely checking are on the first page
  const typeBills = bills.filter(b => b.type === activeType).sort((a, b) => applyOrder(order, compareBills(a, b)));
  const info = analysis[activeType];
  const pageCount = Math.max(1, Math.ceil(typeBills.length / PAGE_SIZE));
  const page = Math.min(pages[activeType] ?? 1, pageCount);

  useEffect(() => {
    writeParams({ tab: activeType === BILL_TYPES[0] ? null : activeType, page: page === 1 ? null : page, order: order === 'desc' ? null : order });
  }, [activeType, page, order]);
  const visible = typeBills.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
  // A gap sits between two bills: above the newer one when oldest-first, below it when newest-first
  const gapBefore = new Map(info.gaps.map(g => [g.before, g]));
  const overlapping = new Set(info.overlaps.map(o => o.second));
  // A bill that is not an advance itself but spans advance invoices is their settlement
  const advancesWithin = (bill: Bill) => typeBills.filter(a => a.advance && a.id !== bill.id && a.period_start >= bill.period_start && a.period_end <= bill.period_end);
  const unpaidCount = (type: BillType) => bills.filter(b => b.type === type && b.transaction_ids.length === 0).length;

  return (
    <div className="mb-4">
      <ul className="nav nav-tabs mb-3" role="tablist">
        {BILL_TYPES.map(type => {
          const issues = analysis[type].gaps.length + analysis[type].overlaps.length;
          const unpaid = unpaidCount(type);
          return (
            <li className="nav-item" role="presentation" key={type}>
              <button
                type="button"
                role="tab"
                aria-selected={type === activeType}
                className={classNames('nav-link', { active: type === activeType })}
                onClick={() => { setActiveType(type); setEditing(null); }}
              >
                {t(`type-${type}`)}
                {unpaid > 0 && <span className="badge bg-warning text-dark ms-2" title={t('status-unpaid')}>{unpaid}</span>}
                {issues > 0 && <span className="badge bg-danger ms-1" title={t('period-issues')}>!</span>}
              </button>
            </li>
          );
        })}
      </ul>

      {typeBills.length === 0 ? <p className="text-body-secondary">{t('no-bills')}</p> : (
        <div className="table-responsive">
          <table className="table table-bordered align-middle bills-list">
            <thead>
              <tr>
                <SortHeader label={t('period')} order={order} onToggle={() => { setOrder(order === 'asc' ? 'desc' : 'asc'); setPages({}); }} />
                <th>{t('amount')}</th>
                <th>{t('due-date')}</th>
                <th>{t('invoice-number')}</th>
                <th>{t('file-date')}</th>
                <th>{t('status')}</th>
                <th>{t('actions')}</th>
              </tr>
            </thead>
            <tbody>
              {order === 'desc' && page === 1 && info.trailing && (
                <tr>
                  <td colSpan={7} className="text-info-emphasis">{t('trailing', { from: formatDate(info.trailing.from), days: info.trailing.days })}</td>
                </tr>
              )}
              {visible.map(bill => {
                const status = statusOf(bill);
                const gap = gapBefore.get(bill.id);
                const gapRow = gap && (
                  <tr key={`gap-${bill.id}`}>
                    <td colSpan={7} className="text-danger-emphasis fw-semibold">{t('gap', { from: formatDate(gap.from), to: formatDate(gap.to) })}</td>
                  </tr>
                );
                return [
                  order === 'asc' && gapRow,
                  editing === bill.id ? (
                    <EditRow
                      key={bill.id}
                      bill={bill}
                      onCancel={() => setEditing(null)}
                      onSave={async updated => {
                        if (await onUpdate(bill.id, updated)) setEditing(null);
                      }}
                    />
                  ) : (
                    <tr key={bill.id}>
                      <td>
                        {formatPeriod(bill)}
                        {bill.advance && <div className="small text-body-secondary">{t('advance-invoice-label')}</div>}
                        {!bill.advance && advancesWithin(bill).length > 0 && (
                          <div className="small text-body-secondary">{t('settlement-covers', { count: advancesWithin(bill).length })}</div>
                        )}
                        {overlapping.has(bill.id) && <div className="small text-warning-emphasis fw-semibold">{t('overlap')}</div>}
                      </td>
                      <td className="text-end">{formatMoney(bill.amount)}</td>
                      <td>{bill.due_date ? formatDate(bill.due_date) : '—'}</td>
                      <td>{bill.invoice_number ?? '—'}</td>
                      <td>{bill.file_modified_at ? formatDate(bill.file_modified_at) : '—'}</td>
                      <td>
                        <span className={`badge ${STATUS_CLASS[status]}`}>{t(`status-${status}`)}</span>
                        {bill.transaction_ids.length > 1 && (
                          <div className="small text-warning-emphasis fw-semibold">{t('paid-times', { count: bill.transaction_ids.length })}</div>
                        )}
                      </td>
                      <td className="text-nowrap">
                        <button type="button" className="btn btn-sm btn-secondary me-1" onClick={() => setEditing(bill.id)}>{t('edit')}</button>
                        <button type="button" className="btn btn-sm btn-danger" onClick={() => onDelete(bill.id)}>{t('delete')}</button>
                      </td>
                    </tr>
                  ),
                  order === 'desc' && gapRow,
                ];
              })}
              {order === 'asc' && page === pageCount && info.trailing && (
                <tr>
                  <td colSpan={7} className="text-info-emphasis">{t('trailing', { from: formatDate(info.trailing.from), days: info.trailing.days })}</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
      <Pagination page={page} pages={pageCount} onPage={n => { setPages({ ...pages, [activeType]: n }); setEditing(null); }} />
    </div>
  );
}
