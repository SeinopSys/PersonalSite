import { useRef, useState } from 'preact/hooks';
import { analyzeFile, revokePreviews } from './analyzeFiles';
import { findExisting } from './findBill';
import {
  formatDate, formatMoney, formatPeriod, t, todayIso,
} from './format';
import { payableAmount } from './matching';
import { ReviewTable } from './ReviewTable';
import { BankTransaction, Bill, DraftBill } from './types';

interface FindBillProps {
  bills: Bill[];
  transactions: BankTransaction[];
  onRecord: (draft: DraftBill) => Promise<boolean>;
  onLink: (transaction: BankTransaction, billId: string) => Promise<boolean>;
  onCreateAndLink: (date: string, amount: number, billId: string) => Promise<boolean>;
}

const DAY = 86400000;
const dayNumber = (iso: string): number => {
  const [y, m, d] = iso.split('-').map(Number);
  return Math.round(Date.UTC(y, m - 1, d) / DAY);
};

/** Transactions that could have paid the bill: on or shortly after the day its file was saved, and big enough. */
const likelyTransactions = (bill: Bill, transactions: BankTransaction[]): BankTransaction[] => {
  if (!bill.file_modified_at) return [];
  const fileDay = dayNumber(bill.file_modified_at);
  return transactions
    .filter(tx => !tx.bill_ids.includes(bill.id) && !tx.accounted && tx.amount >= payableAmount(bill))
    .map(tx => ({ tx, delta: dayNumber(tx.date) - fileDay }))
    .filter(({ delta }) => delta >= -3 && delta <= 30)
    .sort((a, b) => Math.abs(a.delta) - Math.abs(b.delta))
    .slice(0, 5)
    .map(({ tx }) => tx);
};

function Detail({ label, children }: { label: string; children: preact.ComponentChildren }) {
  return (
    <>
      <dt className="col-sm-4 col-lg-3">{label}</dt>
      <dd className="col-sm-8 col-lg-9">{children}</dd>
    </>
  );
}

function Assign({
  bill, transactions, onLink, onCreateAndLink,
}: { bill: Bill } & Pick<FindBillProps, 'transactions' | 'onLink' | 'onCreateAndLink'>) {
  const [date, setDate] = useState(bill.file_modified_at ?? todayIso());
  const [amount, setAmount] = useState('');
  const [searched, setSearched] = useState<{ date: string; amount: number } | null>(null);
  const [working, setWorking] = useState(false);

  const likely = likelyTransactions(bill, transactions);
  const amountValid = /^\d+$/.test(amount.trim());
  const exact = searched ? transactions.filter(tx => tx.date === searched.date && tx.amount === searched.amount) : [];

  const link = async (tx: BankTransaction) => {
    setWorking(true);
    await onLink(tx, bill.id);
    setWorking(false);
  };

  const describe = (tx: BankTransaction) => `${formatDate(tx.date)}: ${formatMoney(tx.amount)}`;

  return (
    <div className="mt-3">
      <h5>{t('assign-heading')}</h5>
      {likely.length > 0 && (
        <div className="mb-3">
          <div className="form-label">{t('likely-transactions')}</div>
          {likely.map(tx => (
            <div key={tx.id} className="d-flex gap-2 align-items-center mb-1">
              <span>{describe(tx)}</span>
              <button type="button" className="btn btn-sm btn-outline-primary" disabled={working} onClick={() => link(tx)}>{t('link')}</button>
            </div>
          ))}
        </div>
      )}
      <form
        className="row g-2 align-items-end"
        onSubmit={e => {
          e.preventDefault();
          if (amountValid && date) setSearched({ date, amount: Number(amount.trim()) });
        }}
      >
        <div className="col-sm-4 col-lg-3">
          <label className="form-label" htmlFor="find-tx-date">{t('paid-on')}</label>
          <input id="find-tx-date" type="date" required className="form-control" value={date} onChange={e => { setDate(e.currentTarget.value); setSearched(null); }} />
        </div>
        <div className="col-sm-4 col-lg-3">
          <label className="form-label" htmlFor="find-tx-amount">{t('amount')}</label>
          <div className="input-group">
            <input
              id="find-tx-amount"
              type="text"
              inputMode="numeric"
              required
              className={`form-control${amount && !amountValid ? ' is-invalid' : ''}`}
              value={amount}
              onChange={e => { setAmount(e.currentTarget.value); setSearched(null); }}
            />
            <span className="input-group-text">Ft</span>
          </div>
        </div>
        <div className="col-sm-auto">
          <button type="submit" className="btn btn-secondary" disabled={!amountValid || !date}>{t('find-transaction')}</button>
        </div>
      </form>
      {searched && (
        <div className="mt-2">
          {exact.map(tx => (
            <div key={tx.id} className="d-flex gap-2 align-items-center mb-1">
              <span>{describe(tx)}</span>
              {tx.bill_ids.includes(bill.id)
                ? <span className="text-body-secondary">{t('already-linked-here')}</span>
                : <button type="button" className="btn btn-sm btn-primary" disabled={working} onClick={() => link(tx)}>{t('link')}</button>}
            </div>
          ))}
          {exact.length === 0 && (
            <div className="d-flex gap-2 align-items-center flex-wrap">
              <span className="text-body-secondary">{t('tx-not-found')}</span>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={working}
                onClick={async () => {
                  setWorking(true);
                  if (await onCreateAndLink(searched.date, searched.amount, bill.id)) setSearched(null);
                  setWorking(false);
                }}
              >
                {t('create-and-link')}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export function FindBill({
  bills, transactions, onRecord, onLink, onCreateAndLink,
}: FindBillProps) {
  const [draft, setDraft] = useState<DraftBill | null>(null);
  const [busy, setBusy] = useState(false);
  const [saving, setSaving] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  const close = () => {
    if (draft) revokePreviews([draft]);
    setDraft(null);
  };

  const pick = async (file: File | undefined) => {
    if (!file) return;
    close();
    setBusy(true);
    setDraft(await analyzeFile(file, 'electricity'));
    setBusy(false);
  };

  const found = draft ? findExisting(draft, bills) : null;
  const linkedTransactions = found ? transactions.filter(tx => found.bill.transaction_ids.includes(tx.id)) : [];

  return (
    <div className="card card-body mb-4">
      <h4>{t('find-bill')}</h4>
      <p className="text-body-secondary mb-2">{t('find-bill-help')}</p>
      <div className="d-flex gap-2 align-items-center flex-wrap">
        <button type="button" className="btn btn-outline-primary" disabled={busy} onClick={() => fileInput.current?.click()}>
          {busy ? t('loading') : t('choose-file')}
        </button>
        <input
          ref={fileInput}
          type="file"
          hidden
          accept="application/pdf,.pdf,image/*"
          onChange={e => {
            pick(e.currentTarget.files?.[0]);
            e.currentTarget.value = '';
          }}
        />
        {draft && <button type="button" className="btn btn-link" onClick={close}>{t('close-result')}</button>}
      </div>

      {draft && !busy && (found ? (
        <div className="mt-3">
          <p className="text-success-emphasis fw-semibold mb-2">{t('recorded')}</p>
          <p className="text-body-secondary small">{t(`found-by-${found.by}`)}</p>
          <dl className="row mb-0">
            <Detail label={t('bill-type')}>{t(`type-${found.bill.type}`)}</Detail>
            <Detail label={t('period')}>{formatPeriod(found.bill)}</Detail>
            <Detail label={t('amount')}>{formatMoney(found.bill.amount)}</Detail>
            {found.bill.credit_applied > 0 && <Detail label={t('credit-applied')}>{formatMoney(found.bill.credit_applied)}</Detail>}
            {found.bill.advance && <Detail label={t('invoice-kind')}>{t('advance-invoice-label')}</Detail>}
            <Detail label={t('due-date')}>{found.bill.due_date ? formatDate(found.bill.due_date) : '—'}</Detail>
            <Detail label={t('invoice-number')}>{found.bill.invoice_number ?? '—'}</Detail>
            <Detail label={t('file-date')}>{found.bill.file_modified_at ? formatDate(found.bill.file_modified_at) : '—'}</Detail>
            <Detail label={t('linked-transactions')}>
              {linkedTransactions.length === 0
                ? <span className="text-warning-emphasis">{t('not-linked-yet')}</span>
                : linkedTransactions.map(tx => <div key={tx.id}>{`${formatDate(tx.date)}: ${formatMoney(tx.amount)}`}</div>)}
            </Detail>
          </dl>
          <Assign key={found.bill.id} bill={found.bill} transactions={transactions} onLink={onLink} onCreateAndLink={onCreateAndLink} />
        </div>
      ) : (
        <div className="mt-3">
          <p className="text-danger-emphasis fw-semibold mb-2">{t('not-recorded')}</p>
          <ReviewTable
            drafts={[draft]}
            saved={bills}
            saving={saving}
            onChange={(_, patch) => setDraft({ ...draft, ...patch })}
            onSave={async () => {
              setSaving(true);
              await onRecord(draft);
              setSaving(false);
            }}
            onDiscard={close}
          />
        </div>
      ))}
    </div>
  );
}
