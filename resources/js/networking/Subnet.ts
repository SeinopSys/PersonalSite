import { rangeLimit, translatePlaceholders } from '../utils';
import { fitsIntoPowerOf2 } from './common';
import { ValidationError } from './ValidationError';

export class Subnet {
  public name = '';

  public empty = false;

  public minimumPCs = 0;

  public minimumIPs = 0;

  public addressCount = 0;

  constructor(line: string, ipv6: boolean) {
    const split = line.trim().replace(/(?:^|\s+)\/\/.*$/, '');
    if (split.length === 0) {
      this.empty = true;
      return;
    }
    let initialMatch = split.match(/^\s*(.*)\s+(\d+|\/\d{1,3})(?:\s+(gép|eszköz|ips?|pcs?|devices?))?\s*$/i);
    if (!initialMatch) throw new ValidationError(
      translatePlaceholders(window.Laravel.jsLocales.vlsm_error_subnet_line_invalid, {
        line,
        why: window.Laravel.jsLocales.vlsm_error_subnet_line_invalid_format,
      }),
    );
    let splitMatch = initialMatch.slice(1);
    if (splitMatch.length < 2) throw new ValidationError(
      translatePlaceholders(window.Laravel.jsLocales.vlsm_error_subnet_line_invalid, {
        line,
        why: window.Laravel.jsLocales.vlsm_error_subnet_line_invalid_count,
      }),
    );
    [this.name] = splitMatch;

    let number;
    if (splitMatch[1][0] === '/') {
      const cap = ipv6 ? 128 : 32;
      number = 2 ** (cap - rangeLimit(parseInt(splitMatch[1].substring(1), 10), false, 0, cap));
      splitMatch[2] = 'ip';
    } else number = parseInt(splitMatch[1], 10);
    if (/^ips?$/.test(splitMatch[2])) {
      this.minimumPCs = number - 2;
      this.minimumIPs = number;
    } else {
      this.minimumPCs = number;
      this.minimumIPs = number + 2;
    }
    this.addressCount = fitsIntoPowerOf2(this.minimumIPs);
  }

  toString() {
    return translatePlaceholders(window.Laravel.jsLocales.vlsm_subnet_tostring, {
      name: this.name,
      addresscount: this.addressCount,
      usable: Math.max(0, this.addressCount - 2),
    });
  }
}
