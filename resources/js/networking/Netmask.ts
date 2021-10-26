import { translatePlaceholders } from '../utils';
import { bindec, binhex, ipPad } from './common';
import { ValidationError } from './ValidationError';

export class Netmask {
  public ipv6: boolean;

  public length: number;

  constructor(length: string | number, skipValidation = false, ipv6 = false) {
    this.ipv6 = ipv6;
    this.length = typeof length === 'number' ? length : parseInt(length, 10);
    if (!skipValidation) {
      const max = ipv6 ? 128 : 32;
      const min = 0;
      if (this.length > max || this.length < min) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_mask_length_invalid, {
          max,
          min,
        }),
      );
    }
  }

  toString() {
    return this.length.toString();
  }

  getDecimal() {
    return (this.ipv6 ? binhex : bindec)(this.getBinary());
  }

  getReverseDecimal() {
    return (this.ipv6 ? binhex : bindec)(this.getReverseBinary());
  }

  /**
   * Returns an easy to understand abbreviation based on length
   * Multiples of 8 and 30 are returned as numbers
   * Any other length is returned without preceeding 255 octets
   */
  getAbbrev() {
    if (this.ipv6 || this.length === 30 || this.length % 8 === 0) return this.length;

    return this.getDecimal().replace(/^(?:255(\.))+/, '$1');
  }

  private separateBinary(binary: string) {
    const matches = binary.match(new RegExp(`.{${this.ipv6 ? 16 : 8}}`, 'g'));
    if (!matches) throw new Error('Expected matches');
    return matches.join(this.ipv6 ? ':' : '.');
  }

  getBinary(withSeparators = false) {
    let binary = ipPad(Array(this.length + 1).join('1'), true, this.ipv6);
    if (withSeparators) binary = this.separateBinary(binary);
    return binary;
  }

  getReverseBinary(withSeparators = false) {
    let binary = ipPad(Array(this.length + 1).join('0'), true, this.ipv6, '1');
    if (withSeparators) binary = this.separateBinary(binary);
    return binary;
  }
}
