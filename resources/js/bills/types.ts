export const BILL_TYPES = ['sewage', 'heating', 'electricity', 'water'] as const;
export type BillType = typeof BILL_TYPES[number];

export interface Bill {
  id: string;
  type: BillType;
  sha256: string | null;
  invoice_number: string | null;
  period_start: string;
  period_end: string;
  due_date: string | null;
  /** Modification date of the uploaded file, used to suggest matching transactions */
  file_modified_at: string | null;
  /** Advance invoice (részszámla): an estimated charge inside the period a later settlement bill covers */
  advance: boolean;
  amount: number;
  transaction_ids: string[];
}

export interface BankTransaction {
  id: string;
  date: string;
  amount: number;
  note: string | null;
  /** Known to be accounted for without bills (a one-off), so it's skipped by the unaccounted check */
  accounted: boolean;
  /** Part of the amount explained by something other than a bill, deducted before the fee is worked out */
  accounted_amount: number | null;
  /** Transactions sharing a group id are one payment (instalments, split transfers) */
  group_id: string | null;
  bill_ids: string[];
}

export interface PeriodAnalysis {
  gaps: { from: string; to: string; after: string; before: string }[];
  overlaps: { first: string; second: string }[];
  trailing: { from: string; days: number } | null;
}

export interface BillsData {
  bills: Bill[];
  transactions: BankTransaction[];
  analysis: Record<BillType, PeriodAnalysis>;
}

/** A bill awaiting review before it's saved. Amount stays a string while it's being edited. */
export interface DraftBill {
  key: number;
  fileName: string | null;
  type: BillType;
  sha256: string | null;
  invoiceNumber: string;
  periodStart: string;
  periodEnd: string;
  dueDate: string;
  fileModified: string;
  advance: boolean;
  amount: string;
  /** Problems found while reading the file, as locale keys */
  parseWarning?: string;
  /** Non-blocking hint, as a locale key */
  note?: string;
  /** Local blob: URL of an image file, shown full size so its figures can be read off. Never leaves the browser. */
  previewUrl?: string;
}
