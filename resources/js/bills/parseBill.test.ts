/* eslint-disable import/no-extraneous-dependencies */
import { describe, expect, it } from 'vitest';
import {
  findAmount, findDueDate, findInvoiceNumber, findPeriod, findPeriodInFileName, isAdvanceInvoice, normalizeDate, parseBillText, sha256Hex,
} from './parseBill';

describe('findPeriod', () => {
  it.each([
    ['Period 2025.01.01. - 2025.01.31. total', '2025-01-01', '2025-01-31'],
    ['Period 2025.01.01-2025.01.31 total', '2025-01-01', '2025-01-31'],
    ['Period 2025-02-01 – 2025-02-28', '2025-02-01', '2025-02-28'],
    ['2025.03.01.-2025.03.31.', '2025-03-01', '2025-03-31'],
  ])('parses %s', (text, start, end) => {
    expect(findPeriod(text)).toEqual({ start, end });
  });

  it('uses the first range', () => {
    expect(findPeriod('2025.01.01 - 2025.01.31 and 2024.01.01 - 2024.12.31')).toEqual({ start: '2025-01-01', end: '2025-01-31' });
  });

  it('skips impossible dates', () => {
    expect(findPeriod('2025.13.01 - 2025.13.31 then 2025.04.01 - 2025.04.30')).toEqual({ start: '2025-04-01', end: '2025-04-30' });
  });

  it('returns null without a range', () => {
    expect(findPeriod('only a single date 2025.01.01. here')).toBeNull();
  });
});

describe('findPeriodInFileName', () => {
  it.each([
    ['2025.08.24-2025.09.23.jpg'],
    ['2025.08.24-2025.09.23.pdf'],
    ['2025.08.24\u00ad2025.09.23.jpg'],
    ['2025.08.24_2025.09.23.jpeg'],
    ['2025-08-24 - 2025-09-23.png'],
    ['Electricity 2025.08.24 \u2013 2025.09.23 final.jpg'],
  ])('reads the period from %s', name => {
    expect(findPeriodInFileName(name)).toEqual({ start: '2025-08-24', end: '2025-09-23' });
  });

  it('returns null for names without a range', () => {
    expect(findPeriodInFileName('scan0001.jpg')).toBeNull();
    expect(findPeriodInFileName('2025.08.24.jpg')).toBeNull();
  });
});

describe('findAmount', () => {
  it.each([
    ['Fizetendő: 1 234 Ft', 1234],
    ['Fizetendő: 12 345 Ft', 12345],
    ['Fizetendő: 12 345 Ft', 12345],
    ['Fizetendő: 12.345 Ft', 12345],
    ['Fizetendő: 1.234 Ft', 1234],
    ['Fizetendő: 12,345 Ft', 12345],
    ['Fizetendő: 1 234 567 Ft', 1234567],
    ['Fizetendő: 950Ft', 950],
    ['Rounding: -99 Ft', -99],
  ])('parses %s', (text, expected) => {
    expect(findAmount(text)).toBe(expected);
  });

  it('uses the first amount', () => {
    expect(findAmount('5 000 Ft then 9 999 Ft')).toBe(5000);
  });

  it('does not start inside a longer number', () => {
    expect(findAmount('total 12345 Ft')).toBe(12345);
  });

  it('ignores words that merely start with Ft', () => {
    expect(findAmount('12 345 Ftx')).toBeNull();
  });

  it('returns null without an amount', () => {
    expect(findAmount('nothing payable')).toBeNull();
  });
});

describe('labeled fields', () => {
  it('finds the due date', () => {
    expect(findDueDate('Fizetési határidő: 2025.02.15.')).toBe('2025-02-15');
    expect(findDueDate('Fizetési határidő\n2025-02-15')).toBe('2025-02-15');
    expect(findDueDate('Fizet é si hat á rid ő : 2025.02.15.')).toBe('2025-02-15');
    expect(findDueDate('no label 2025.02.15.')).toBeNull();
  });

  it('finds both observed invoice number shapes', () => {
    expect(findInvoiceNumber('Számla sorszáma: ABC/12345678')).toBe('ABC/12345678');
    expect(findInvoiceNumber('Számla sorszáma 123456789012 ')).toBe('123456789012');
    expect(findInvoiceNumber('Sz á mla sorsz á ma: ABC/12345678 next')).toBe('ABC/12345678');
    expect(findInvoiceNumber('nothing')).toBeNull();
  });
});

describe('parseBillText', () => {
  it('combines the fields', () => {
    const text = 'Számla sorszáma: ABC/12345678\nFizetési határidő: 2025.02.15.\n2025.01.01. - 2025.01.31.\nFizetendő: 12 345 Ft';
    expect(parseBillText(text)).toEqual({
      periodStart: '2025-01-01',
      periodEnd: '2025-01-31',
      amount: 12345,
      dueDate: '2025-02-15',
      invoiceNumber: 'ABC/12345678',
      advance: false,
    });
  });

  it('returns nulls for unrelated text', () => {
    expect(parseBillText('hello')).toEqual({
      periodStart: null, periodEnd: null, amount: null, dueDate: null, invoiceNumber: null, advance: false,
    });
  });
});

describe('isAdvanceInvoice', () => {
  it('recognises an advance invoice', () => {
    expect(isAdvanceInvoice('Víz- és csatornadíj részszámla 2024.09.05 - 2024.10.29')).toBe(true);
    expect(isAdvanceInvoice('rész szám la')).toBe(true);
  });

  it('does not treat a settlement or a bill with both words as an advance', () => {
    expect(isAdvanceInvoice('Elszámoló számla')).toBe(false);
    expect(isAdvanceInvoice('részszámla ... elszámoló')).toBe(false);
    expect(isAdvanceInvoice('Villamos energia számla')).toBe(false);
  });
});

describe('normalizeDate', () => {
  it('rejects non-dates', () => {
    expect(normalizeDate('2025.02.30.')).toBeNull();
    expect(normalizeDate('garbage')).toBeNull();
  });
});

describe('sha256Hex', () => {
  it('matches the known vector for "abc"', async () => {
    const data = new TextEncoder().encode('abc');
    expect(await sha256Hex(data.buffer)).toBe('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
  });
});
