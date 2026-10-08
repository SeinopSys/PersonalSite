import { Fragment } from 'preact';
import { Bill, BILL_TYPES, DraftBill } from './types';
import { t } from './format';

/** The saved bill a draft repeats: same file, same invoice number, or same type, period and amount. */
export function findSavedDuplicate(draft: DraftBill, saved: Bill[]): Bill | undefined {
  return saved.find(b => (draft.sha256 && b.sha256 === draft.sha256)
    || (draft.invoiceNumber && b.invoice_number === draft.invoiceNumber)
    || (draft.periodStart && draft.amount.trim() !== ''
      && b.type === draft.type && b.period_start === draft.periodStart && b.period_end === draft.periodEnd && String(b.amount) === draft.amount.trim()));
}

export type DraftIssue = { text: string; blocking: boolean };

export function draftIssues(draft: DraftBill, saved: Bill[], earlier: DraftBill[]): DraftIssue[] {
  const issues: DraftIssue[] = [];
  if (draft.parseWarning) issues.push({ text: t(draft.parseWarning), blocking: true });
  if (!draft.periodStart || !draft.periodEnd) issues.push({ text: t('warn-no-period'), blocking: true });
  else if (draft.periodEnd < draft.periodStart) issues.push({ text: t('warn-bad-range'), blocking: true });
  if (draft.amount.trim() === '' || !/^-?\d+$/.test(draft.amount.trim())) issues.push({ text: t('warn-no-amount'), blocking: true });
  if (findSavedDuplicate(draft, saved)) issues.push({ text: t('warn-duplicate-sha256'), blocking: true });
  if (earlier.some(d => (draft.sha256 && d.sha256 === draft.sha256)
    || (draft.invoiceNumber && d.invoiceNumber === draft.invoiceNumber)
    || (draft.periodStart && d.type === draft.type && d.periodStart === draft.periodStart && d.periodEnd === draft.periodEnd
      && d.amount.trim() === draft.amount.trim() && draft.amount.trim() !== ''))) {
    issues.push({ text: t('warn-duplicate-batch'), blocking: true });
  }
  return issues;
}

interface ReviewTableProps {
  drafts: DraftBill[];
  saved: Bill[];
  saving: boolean;
  onChange: (key: number, patch: Partial<DraftBill>) => void;
  onSave: () => void;
  onDiscard: () => void;
}

export function ReviewTable({
  drafts, saved, saving, onChange, onSave, onDiscard,
}: ReviewTableProps) {
  const issues = drafts.map((d, i) => draftIssues(d, saved, drafts.slice(0, i)));
  const saveable = issues.filter(list => !list.some(issue => issue.blocking)).length;

  return (
    <div className="mb-4">
      <h3>{t('review')}</h3>
      <div className="table-responsive">
        <table className="table table-bordered align-top bills-review">
          <thead>
            <tr>
              <th>{t('file')}</th>
              <th>{t('period')}</th>
              <th>{t('amount')}</th>
              <th>{t('invoice-number')}</th>
            </tr>
          </thead>
          <tbody>
            {drafts.map((draft, i) => (
              <Fragment key={draft.key}>
                <tr>
                  <td className="bills-filename">
                    <div className="fw-semibold mb-1">{draft.fileName ?? '—'}</div>
                    {issues[i].map(issue => <div key={issue.text} className="text-danger small">{issue.text}</div>)}
                    {draft.note && <div className="text-body-secondary small">{t(draft.note)}</div>}
                    <select
                      className="form-select form-select-sm mt-1"
                      aria-label={t('bill-type')}
                      value={draft.type}
                      onChange={e => onChange(draft.key, { type: e.currentTarget.value as DraftBill['type'] })}
                    >
                      {BILL_TYPES.map(type => <option key={type} value={type}>{t(`type-${type}`)}</option>)}
                    </select>
                  </td>
                  <td>
                    <input
                      type="date"
                      className="form-control form-control-sm mb-1"
                      aria-label={t('period-start')}
                      title={t('period-start')}
                      value={draft.periodStart}
                      onChange={e => onChange(draft.key, { periodStart: e.currentTarget.value })}
                    />
                    <input
                      type="date"
                      className="form-control form-control-sm"
                      aria-label={t('period-end')}
                      title={t('period-end')}
                      value={draft.periodEnd}
                      onChange={e => onChange(draft.key, { periodEnd: e.currentTarget.value })}
                    />
                  </td>
                  <td>
                    <div className="input-group input-group-sm mb-1">
                      <input
                        type="text"
                        inputMode="numeric"
                        className="form-control"
                        aria-label={t('amount')}
                        value={draft.amount}
                        onChange={e => onChange(draft.key, { amount: e.currentTarget.value })}
                      />
                      <span className="input-group-text">Ft</span>
                    </div>
                    <div className="small text-body-secondary">{t('due-date')}</div>
                    <input
                      type="date"
                      className="form-control form-control-sm"
                      aria-label={t('due-date')}
                      value={draft.dueDate}
                      onChange={e => onChange(draft.key, { dueDate: e.currentTarget.value })}
                    />
                  </td>
                  <td>
                    <input
                      type="text"
                      className="form-control form-control-sm mb-1"
                      aria-label={t('invoice-number')}
                      value={draft.invoiceNumber}
                      onChange={e => onChange(draft.key, { invoiceNumber: e.currentTarget.value })}
                    />
                    <div className="small text-body-secondary">{t('file-date')}</div>
                    <input
                      type="date"
                      className="form-control form-control-sm"
                      aria-label={t('file-date')}
                      value={draft.fileModified}
                      onChange={e => onChange(draft.key, { fileModified: e.currentTarget.value })}
                    />
                    <div className="form-check mt-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id={`advance-${draft.key}`}
                        checked={draft.advance}
                        onChange={e => onChange(draft.key, { advance: e.currentTarget.checked })}
                      />
                      <label className="form-check-label small" htmlFor={`advance-${draft.key}`}>{t('advance-invoice')}</label>
                    </div>
                  </td>
                </tr>
                {draft.previewUrl && (
                  <tr>
                    <td colSpan={4} className="bills-fullimage">
                      <img src={draft.previewUrl} alt={draft.fileName ?? t('file-preview')} />
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>
      <div className="d-flex gap-2">
        <button type="button" className="btn btn-primary" disabled={saving || saveable === 0} onClick={onSave}>
          {t('save-bills')}
          {' '}
          (
          {saveable}
          /
          {drafts.length}
          )
        </button>
        <button type="button" className="btn btn-secondary" disabled={saving} onClick={onDiscard}>{t('discard')}</button>
      </div>
    </div>
  );
}
