import { isoFromMillis } from './format';
import { extractPdfText } from './pdf';
import { findPeriodInFileName, parseBillText, sha256Hex } from './parseBill';
import { BillType, DraftBill } from './types';

let nextKey = 1;

export const emptyDraft = (type: BillType): DraftBill => {
  nextKey += 1;
  return {
    key: nextKey, fileName: null, type, sha256: null, invoiceNumber: '', periodStart: '', periodEnd: '', dueDate: '', fileModified: '', advance: false, amount: '',
  };
};

/** Hashes and parses one file. Everything happens in the browser, nothing is uploaded. */
export async function analyzeFile(file: File, type: BillType): Promise<DraftBill> {
  // The file's modification date is read here, in the browser, and used to suggest matching transactions
  const draft = { ...emptyDraft(type), fileName: file.name, fileModified: isoFromMillis(file.lastModified) };
  const isImage = /\.(jpe?g|png|webp)$/i.test(file.name) || file.type.startsWith('image/');
  if (!isImage && !/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') {
    return { ...draft, parseWarning: 'warn-not-pdf' };
  }
  if (isImage) {
    // Scans can't be read automatically; keep the hash and file date, and take the period from the file name if it has one
    try {
      const named = findPeriodInFileName(file.name);
      return {
        ...draft,
        sha256: await sha256Hex(await file.arrayBuffer()),
        periodStart: named?.start ?? '',
        periodEnd: named?.end ?? '',
        note: named ? 'note-image-period-from-name' : 'note-image-manual',
        previewUrl: URL.createObjectURL(file),
      };
    } catch {
      return { ...draft, parseWarning: 'warn-parse-failed' };
    }
  }
  try {
    const buffer = await file.arrayBuffer();
    const sha256 = await sha256Hex(buffer);
    const parsed = parseBillText(await extractPdfText(buffer));
    // The file name is only a fallback for when the document itself has no period
    const named = parsed.periodStart ? null : findPeriodInFileName(file.name);
    return {
      ...draft,
      sha256,
      invoiceNumber: parsed.invoiceNumber ?? '',
      periodStart: parsed.periodStart ?? named?.start ?? '',
      periodEnd: parsed.periodEnd ?? named?.end ?? '',
      note: named ? 'note-period-from-name' : undefined,
      dueDate: parsed.dueDate ?? '',
      advance: parsed.advance,
      amount: parsed.amount === null ? '' : String(parsed.amount),
    };
  } catch {
    return { ...draft, parseWarning: 'warn-parse-failed' };
  }
}

/** Frees the memory behind the preview of drafts that are no longer needed. */
export const revokePreviews = (drafts: DraftBill[]): void => {
  drafts.forEach(d => { if (d.previewUrl) URL.revokeObjectURL(d.previewUrl); });
};
