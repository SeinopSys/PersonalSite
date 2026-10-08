import { translatePlaceholders } from '../utils';
import { Bill } from './types';

export const t = (key: string, params?: Record<string, string | number>): string => {
  const str = window.Laravel.jsLocales[key];
  return str === undefined ? key : translatePlaceholders(str, params);
};

export const formatMoney = (amount: number): string => new Intl.NumberFormat(window.Laravel.locale, {
  style: 'currency',
  currency: 'HUF',
  maximumFractionDigits: 0,
}).format(amount);

// Hungarian style (2024. 10. 08.) whatever the interface language, so dates never read like US ones (10/8/2024)
export const formatDate = (iso: string): string => {
  const [y, m, d] = iso.split('-');
  return `${y}. ${m}. ${d}.`;
};

/** Percentage with a Hungarian decimal comma, e.g. 0,35%. */
export const formatPercent = (value: number): string => `${value.toLocaleString('hu-HU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;

export const formatPeriod = (bill: Pick<Bill, 'period_start' | 'period_end'>): string => `${formatDate(bill.period_start)} – ${formatDate(bill.period_end)}`;

export const isoFromMillis = (millis: number): string => {
  const d = new Date(millis);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export const todayIso = (): string => isoFromMillis(Date.now());
