// Pure text-analysis helpers for utility bills. No DOM or pdf.js access here so they can be unit tested.

const DATE = '\\d{4}[-.]\\d{2}[-.]\\d{2}\\.?';
// Hyphen, other dashes and the soft hyphen (U+00AD) that shows up in some file names
const DASH = '[-\\u00ad\\u2010-\\u2015\\u2212]';
const RANGE_RE = new RegExp(`(?<!\\d)(${DATE})\\s*${DASH}\\s*(${DATE})(?!\\d)`, 'g');
const DATE_RE = new RegExp(`(?<!\\d)(${DATE})(?!\\d)`);
// Thousands may be separated by a space, NBSP, comma or dot. A leading minus is kept as-is.
const AMOUNT_RE = /(?<![\d.,])(-?\d{1,3}(?:[ \u00a0,.]\d{3})+|-?\d+)\s*Ft\b/;
// pdf.js often emits stray spaces around accented glyphs ("sorsz á ma"), so labels are matched ignoring whitespace
const looseLabel = (label: string) => label.replace(/\s+/g, '').split('').join('\\s*');
const DUE_DATE_LABEL_RE = new RegExp(`${looseLabel('Fizetési határidő')}[^\\d]{0,40}(${DATE})`, 'i');
const INVOICE_LABEL_RE = new RegExp(`${looseLabel('Számla sorszáma')}\\s*:?\\s*(\\S+)`, 'i');

// An advance invoice says "részszámla". Most other bills mention "elszámoló" (settlement), and some electricity
// bills carry both words as boilerplate, so a bill only counts as an advance when it has the first and not the second.
const ADVANCE_RE = new RegExp(looseLabel('részszámla'), 'i');
const SETTLEMENT_RE = new RegExp(looseLabel('elszámoló'), 'i');

export function isAdvanceInvoice(text: string): boolean {
  return ADVANCE_RE.test(text) && !SETTLEMENT_RE.test(text);
}

export interface ParsedBill {
  periodStart: string | null;
  periodEnd: string | null;
  amount: number | null;
  dueDate: string | null;
  invoiceNumber: string | null;
  advance: boolean;
}

/** Normalizes `yyyy.mm.dd.`, `yyyy.mm.dd` and `yyyy-mm-dd` to `yyyy-mm-dd`, or null if it isn't a real calendar date. */
export function normalizeDate(raw: string): string | null {
  const m = raw.match(/^(\d{4})[-.](\d{2})[-.](\d{2})\.?$/);
  if (!m) return null;
  const [year, month, day] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const d = new Date(Date.UTC(year, month - 1, day));
  if (d.getUTCFullYear() !== year || d.getUTCMonth() !== month - 1 || d.getUTCDate() !== day) return null;
  return `${m[1]}-${m[2]}-${m[3]}`;
}

/** The first date range in the document is the accounting period. */
export function findPeriod(text: string): { start: string; end: string } | null {
  RANGE_RE.lastIndex = 0;
  let match = RANGE_RE.exec(text);
  while (match) {
    const start = normalizeDate(match[1]);
    const end = normalizeDate(match[2]);
    if (start && end) return { start, end };
    match = RANGE_RE.exec(text);
  }
  return null;
}

/** Fallback for files whose content has no period: the same date range pattern, read from the file name. */
export function findPeriodInFileName(fileName: string): { start: string; end: string } | null {
  // An underscore between the two dates is a common stand-in for the dash
  return findPeriod(fileName.replace(/\.[A-Za-z0-9]{2,5}$/, '').replace(/_+/g, '-'));
}

/** The first "x Ft" amount in the document is the amount to be paid. */
export function findAmount(text: string): number | null {
  const m = text.match(AMOUNT_RE);
  if (!m) return null;
  const value = Number(m[1].replace(/[ \u00a0,.]/g, ''));
  return Number.isSafeInteger(value) ? value : null;
}

export function findDueDate(text: string): string | null {
  const m = text.match(DUE_DATE_LABEL_RE);
  return m ? normalizeDate(m[1]) : null;
}

export function findInvoiceNumber(text: string): string | null {
  const m = text.match(INVOICE_LABEL_RE);
  return m ? m[1] : null;
}

export function findFirstDate(text: string): string | null {
  const m = text.match(DATE_RE);
  return m ? normalizeDate(m[1]) : null;
}

export function parseBillText(text: string): ParsedBill {
  const period = findPeriod(text);
  return {
    periodStart: period?.start ?? null,
    periodEnd: period?.end ?? null,
    amount: findAmount(text),
    dueDate: findDueDate(text),
    invoiceNumber: findInvoiceNumber(text),
    advance: isAdvanceInvoice(text),
  };
}

export async function sha256Hex(data: ArrayBuffer): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', data);
  return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
}

export interface TextItem {
  str: string;
  hasEOL?: boolean;
}

/** Joins the text items of one PDF page in content order, breaking lines where pdf.js reports an end of line. */
export function itemsToText(items: TextItem[]): string {
  return items.map(item => item.str + (item.hasEOL ? '\n' : ' ')).join('');
}
