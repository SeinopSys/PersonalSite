import { translatePlaceholders } from '../utils';
import { BinaryIncrementable } from './BinaryIncrementable';
import { bindec, decbin } from './common';
import { Netmask } from './Netmask';
import { ValidationError } from './ValidationError';

export class IPV4Address extends BinaryIncrementable {
  private parts: number[] = [];

  constructor(dotdec: string) {
    super();
    const parts = dotdec.split('.');
    if (parts.length !== 4) throw new ValidationError(
      translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipadd_format_invalid, { dotdec }),
    );
    parts.forEach((el, i) => {
      const part = parseInt(el, 10);
      if (Number.isNaN(part)) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipadd_octet_invalid, {
          dotdec,
          n: i + 1,
          why: window.Laravel.jsLocales.vlsm_error_ipadd_octet_invalid_nan,
        }),
      );
      if (part > 255 || part < 0) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipadd_octet_invalid, {
          dotdec,
          n: i + 1,
          why: window.Laravel.jsLocales.vlsm_error_ipadd_octet_invalid_range,
        }),
      );
      this.parts[i] = part;
    });
  }

  networkAddress(mask: Netmask) {
    const ipbin = this.getBinary();
    const maskbin = mask.getBinary();
    let netbin = '';

    for (let i = 0, l = maskbin.length; i < l; i++) netbin += maskbin[i] === '1' ? ipbin[i] : maskbin[i];

    return new IPV4Address(bindec(netbin));
  }

  toString(): string {
    return this.parts.join('.');
  }

  getBinary(dotted = false): string {
    return decbin(this.parts, dotted ? '.' : '');
  }

  setBinary(bin: string, dotted = false): this {
    let localBin = bin;
    if (dotted) localBin = localBin.replace(/\./g, '');
    this.constructor(bindec(localBin));
    return this;
  }

  static fromBinary(bin: string, dotted = false): IPV4Address {
    const localBin = dotted ? bin.replace(/\./g, '') : bin;
    return new IPV4Address(bindec(localBin));
  }
}
