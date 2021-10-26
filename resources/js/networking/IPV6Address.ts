import { strRepeat, translatePlaceholders } from '../utils';
import { BinaryIncrementable } from './BinaryIncrementable';
import { binhex, decbin, ipv6trim } from './common';
import { IPV6AddressBlock } from './IPV6AddressBlock';
import { Netmask } from './Netmask';
import { ValidationError } from './ValidationError';

export class IPV6Address extends BinaryIncrementable {
  public blocks: IPV6AddressBlock[];

  constructor(colonhex: string) {
    super();
    // Check for shortening symbol
    if (colonhex.indexOf('::') !== -1) {
      const sections = colonhex.split('::');
      if (sections.length > 2) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_short_invalid, { colonhex }),
      );

      // :: is at the beginning
      if (sections[0].length === 0) {
        // The string ends after :: it's an empty address
        if (sections[1].length === 0) {
          this.blocks = strRepeat('0', 8).split('').map(() => new IPV6AddressBlock());
        } else {
          // We have stuff after :: so parse it
          const blocks = sections[1].split(':');
          this.blocks = [];
          $.each(blocks, (n, el) => {
            this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
          });
          // Fill the beginning with zeroes
          if (this.blocks.length < 8) {
            const prepend = [];
            for (let i = 0; i < 8 - this.blocks.length; i++) prepend.push(new IPV6AddressBlock());
            this.blocks.splice(0, 0, ...prepend);
          } else if (this.blocks.length > 8) throw new ValidationError(
            translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, { colonhex }),
          );
        }
      } else {
        // We have stuff before ::
        const blocks = sections[0].split(':');
        this.blocks = [];
        $.each(blocks, (n, el) => {
          this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
        });
        // String ends after ::
        if (sections[1].length === 0) {
          // Fill the end with zeroes
          if (this.blocks.length < 8) while (this.blocks.length < 8) this.blocks.push(new IPV6AddressBlock());
          else if (this.blocks.length > 8) throw new ValidationError(
            translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, { colonhex }),
          );
        } else {
          // There's more data after ::
          const moreblocks = sections[1].split(':');
          const tmpparts: IPV6AddressBlock[] = [];
          $.each(moreblocks, (n, el) => {
            tmpparts.push(IPV6Address.parseBlock(el, colonhex, n));
          });

          const partsum = this.blocks.length + tmpparts.length;
          if (partsum > 8) throw new ValidationError(
            translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, { colonhex }),
          );
          if (partsum !== 8) for (let i = 0; i < 8 - partsum; i++) this.blocks.push(new IPV6AddressBlock());
          this.blocks = [...this.blocks, ...tmpparts];
        }
      }
    } else {
      // No shortening, parse normally
      const blocks = colonhex.split(':');
      if (blocks.length > 8) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, { colonhex }),
      );
      this.blocks = [];
      $.each(blocks, (n, el) => {
        this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
      });
    }
  }

  static parseBlock(block: string, colonhex: string, n: number): IPV6AddressBlock {
    const value = parseInt(block, 16);

    if (Number.isNaN(value) || !/^[a-f\d]{1,4}$/i.test(block)) {
      throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_block_invalid, {
          colonhex,
          n,
          why: window.Laravel.jsLocales.vlsm_error_ipv6add_block_invalid_nan,
        }),
      );
    } else if (value < 0 || value > 0xFFFF) throw new ValidationError(
      translatePlaceholders(window.Laravel.jsLocales.vlsm_error_ipv6add_block_invalid, {
        colonhex,
        n,
        why: window.Laravel.jsLocales.vlsm_error_ipv6add_block_invalid_range,
      }),
    );

    return new IPV6AddressBlock(value);
  }

  toString(): string {
    return ipv6trim(this.blocks.join(':'));
  }

  getBinary(coloned = false): string {
    return decbin(this.blocks.map(el => el.value), coloned ? ':' : '', true);
  }

  setBinary(bin: string, coloned = false): this {
    const localBin = coloned ? bin.replace(/:/g, '') : bin;
    this.constructor(binhex(localBin));
    return this;
  }

  static fromBinary(bin: string, coloned = false): IPV6Address {
    const localBin = coloned ? bin.replace(/:/g, '') : bin;
    return new IPV6Address(binhex(localBin));
  }

  networkAddress(mask: Netmask): IPV6Address {
    const ipbin = this.getBinary();
    const maskbin = mask.getBinary();
    let netbin = '';

    for (let i = 0, l = maskbin.length; i < l; i++) netbin += maskbin[i] === '1' ? ipbin[i] : maskbin[i];

    return new IPV6Address(binhex(netbin));
  }
}
