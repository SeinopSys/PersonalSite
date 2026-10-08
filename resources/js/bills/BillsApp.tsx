import { useEffect, useState } from 'preact/hooks';
import { Dialog } from '../dialog';
import { api } from './api';
import { analyzeFile, emptyDraft, revokePreviews } from './analyzeFiles';
import { BillList } from './BillList';
import { DropZone } from './DropZone';
import { FindBill } from './FindBill';
import { draftIssues, findSavedDuplicate, ReviewTable } from './ReviewTable';
import { Shares } from './Shares';
import { Transactions } from './Transactions';
import { t } from './format';
import {
  Bill, BillsData, BillType, DraftBill,
} from './types';

interface DataResponse extends BillsData { status: boolean }
interface StoreResponse { status: boolean; created: Bill[]; duplicates: { index: number; backfilled: boolean }[] }

const toPayload = (d: DraftBill) => ({
  type: d.type,
  sha256: d.sha256,
  invoice_number: d.invoiceNumber.trim() || null,
  period_start: d.periodStart,
  period_end: d.periodEnd,
  due_date: d.dueDate || null,
  file_modified_at: d.fileModified || null,
  advance: d.advance,
  amount: Number(d.amount.trim()),
});

const confirmThen = (content: string, action: () => void) => Dialog.confirm({
  title: window.Laravel.dialog.confirm,
  content,
  handlerFunc: sure => {
    if (!sure) return;
    Dialog.close();
    action();
  },
});

export function BillsApp() {
  const [data, setData] = useState<BillsData | null>(null);
  const [type, setType] = useState<BillType>('electricity');
  const [drafts, setDrafts] = useState<DraftBill[]>([]);
  const [busy, setBusy] = useState<string | null>(null);
  // Files wait here until "Analyze" is pressed, so the bill type can still be changed
  const [pending, setPending] = useState<File[]>([]);
  const [saving, setSaving] = useState(false);

  const reload = async () => {
    const response = await api<DataResponse>('GET', '/bills/data');
    if (response) setData({ bills: response.bills, transactions: response.transactions, analysis: response.analysis });
  };

  useEffect(() => { reload(); }, []);

  if (!data) return <p>{t('loading')}</p>;

  const sameFile = (a: File, b: File) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
  const queueFiles = (files: File[]) => setPending(current => [
    ...current,
    ...files.filter((file, i) => !current.some(c => sameFile(c, file)) && files.findIndex(f => sameFile(f, file)) === i),
  ]);

  const analyzePending = async () => {
    const files = pending;
    setPending([]);
    const added: DraftBill[] = [];
    // eslint-disable-next-line no-restricted-syntax
    for (const file of files) {
      setBusy(t('parsing', { name: file.name }));
      // eslint-disable-next-line no-await-in-loop
      added.push(await analyzeFile(file, type));
    }
    setBusy(null);

    // Bills that are already saved never enter the review. Their file dates are filled in if they were saved without one.
    const known = added.filter(d => findSavedDuplicate(d, data.bills));
    const fresh = added.filter(d => !known.includes(d));
    revokePreviews(known);
    setDrafts(current => [...current, ...fresh]);
    if (known.length === 0) return;

    const backfillable = known.filter(d => d.fileModified && d.periodStart && d.periodEnd && /^-?\d+$/.test(d.amount.trim()));
    let filled = 0;
    if (backfillable.length) {
      const response = await api<StoreResponse>('POST', '/bills', { bills: backfillable.map(toPayload) });
      if (response) {
        filled = response.duplicates.filter(d => d.backfilled).length;
        await reload();
      }
    }
    const message = [t('already-saved', { count: known.length })];
    if (filled) message.push(t('dates-filled', { count: filled }));
    Dialog.success(undefined, message.join(', '), true);
  };

  const saveDrafts = async () => {
    const valid = drafts.filter((d, i) => !draftIssues(d, data.bills, drafts.slice(0, i)).some(issue => issue.blocking));
    setSaving(true);
    const response = await api<StoreResponse>('POST', '/bills', {
      bills: valid.map(toPayload),
    });
    setSaving(false);
    if (!response) return;
    const message = [t('saved', { count: response.created.length })];
    if (response.duplicates.length) message.push(t('skipped', { count: response.duplicates.length }));
    // Keep the drafts that weren't saved so nothing silently disappears
    revokePreviews(valid);
    setDrafts(drafts.filter(d => !valid.includes(d)));
    await reload();
    Dialog.success(undefined, message.join(' '), true);
  };

  return (
    <div>
      <DropZone
        type={type}
        busy={busy}
        onTypeChange={setType}
        pending={pending}
        onFiles={queueFiles}
        onRemove={index => setPending(current => current.filter((_, i) => i !== index))}
        onClear={() => setPending([])}
        onAnalyze={analyzePending}
        onManual={() => setDrafts(current => [...current, emptyDraft(type)])}
      />
      <FindBill
        bills={data.bills}
        transactions={data.transactions}
        onRecord={async draft => {
          const response = await api<StoreResponse>('POST', '/bills', { bills: [toPayload(draft)] });
          if (response) await reload();
          return !!response;
        }}
        onLink={async (tx, billId) => {
          const response = await api('PUT', `/bank-transactions/${tx.id}`, {
            date: tx.date,
            amount: tx.amount,
            note: tx.note,
            accounted: tx.accounted,
            accounted_amount: tx.accounted_amount,
            bill_ids: Array.from(new Set([...tx.bill_ids, billId])),
          });
          if (response) await reload();
          return !!response;
        }}
        onCreateAndLink={async (date, amount, billId) => {
          const response = await api('POST', '/bank-transactions', { date, amount, bill_ids: [billId] });
          if (response) await reload();
          return !!response;
        }}
      />
      {drafts.length > 0 && (
        <ReviewTable
          drafts={drafts}
          saved={data.bills}
          saving={saving}
          onChange={(key, patch) => setDrafts(current => current.map(d => (d.key === key ? { ...d, ...patch } : d)))}
          onSave={saveDrafts}
          onDiscard={() => { revokePreviews(drafts); setDrafts([]); }}
        />
      )}
      <BillList
        bills={data.bills}
        analysis={data.analysis}
        onUpdate={async (id, bill) => {
          const response = await api('PUT', `/bills/${id}`, {
            type: bill.type,
            sha256: bill.sha256,
            invoice_number: bill.invoice_number,
            period_start: bill.period_start,
            period_end: bill.period_end,
            due_date: bill.due_date,
            file_modified_at: bill.file_modified_at,
            advance: bill.advance,
            credit_applied: bill.credit_applied,
            credit_source_id: bill.credit_source_id,
            amount: bill.amount,
          });
          if (response) await reload();
          return !!response;
        }}
        onDelete={id => confirmThen(t('confirm-delete-bill'), async () => {
          if (await api('DELETE', `/bills/${id}`)) await reload();
        })}
      />
      <Transactions
        onGroup={async ids => { if (await api('POST', '/bank-transactions/group', { transaction_ids: ids })) await reload(); }}
        onUngroup={async ids => { if (await api('POST', '/bank-transactions/ungroup', { transaction_ids: ids })) await reload(); }}
        transactions={data.transactions}
        bills={data.bills}
        onSave={async (payload, id) => {
          const response = await api(id ? 'PUT' : 'POST', id ? `/bank-transactions/${id}` : '/bank-transactions', payload);
          if (response) await reload();
          return !!response;
        }}
        onDelete={id => confirmThen(t('confirm-delete-transaction'), async () => {
          if (await api('DELETE', `/bank-transactions/${id}`)) await reload();
        })}
      />
      <Shares />
    </div>
  );
}
