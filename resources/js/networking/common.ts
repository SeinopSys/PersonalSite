import { pad } from '../utils';

const octetPad = (bin: string): string => pad(bin, '0', 8);
const blockPad = (bin: string): string => pad(bin, '0', 16);
export const ipPad = (bin: string, fromRight: boolean, ipv6 = false, chr = '0'): string => pad(bin, chr, ipv6 ? 128 : 32, fromRight);
export const ipv6trim = (ipv6: string): string => ipv6.replace(/(^|:)0(:0)*:0(:|$)/, '::');
export const decbin = (dec: string | string[] | number[], separator: string, ipv6 = false): string => {
  const binary: string[] = [];
  const localDec = typeof dec === 'string' ? dec.split(separator) : dec;
  $.each(localDec, (_, el) => {
    binary.push((ipv6 ? blockPad : octetPad)((typeof el === 'string' ? parseInt(el, 10) : el).toString(2)));
  });
  return binary.join(separator);
};
export const bindec = (bin: number | string): string => {
  const dec: number[] = [];
  const localBin = typeof bin === 'number' ? bin.toString(2) : bin;
  const localBinArray = localBin.indexOf('.') === -1 ? localBin.match(/.{8}/g) : localBin.split('.');
  if (!localBinArray) throw new Error('unexpected value');
  $.each(localBinArray, (_, el) => {
    dec.push(parseInt(el, 2));
  });
  return dec.join('.');
};
export const binhex = (bin: number | string): string => {
  const hex: string[] = [];
  const localBin = typeof bin === 'number' ? bin.toString(2) : bin;
  const localBinArray = localBin.indexOf(':') === -1 ? localBin.match(/.{16}/g) : localBin.split(':');
  $.each(localBinArray, (_, el) => {
    hex.push(parseInt(el, 2).toString(16));
  });
  return ipv6trim(hex.join(':'));
};
export const ipversion = (cidr: string): '4' | '6' | false => {
  const parts = cidr.split('/');
  if (parts.length > 2) return false;
  if (/^[\d.]+$/.test(parts[0])) return '4';
  if (/^[\da-f:]+$/i.test(parts[0])) return '6';
  return false;
};
export const fitsIntoPowerOf2 = (n: number): number => 2 ** Math.ceil(Math.log(n) / Math.log(2));
