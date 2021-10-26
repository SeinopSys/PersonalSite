import { translatePlaceholders } from '../utils';
import { Netmask } from './Netmask';
import { Network } from './Network';
import { ValidationError } from './ValidationError';

export type PrefixListAction = 'permit' | 'block';

export class PrefixListEntry {
  public seq: number;

  public action: PrefixListAction;

  public subnet: Network;

  public le: number | null;

  public ge: number | null;

  constructor(opts: { action: PrefixListAction, subnet: Network, seq: string, le?: string, ge?: string }) {
    this.seq = parseInt(opts.seq, 10);
    const [min, max] = [1, 4294967294];
    if (this.seq < min || this.seq > max) throw new ValidationError(
      translatePlaceholders(window.Laravel.jsLocales.prefix_list_error_seq_number_invalid, {
        min,
        max,
      }),
    );

    this.action = opts.action;
    this.subnet = opts.subnet;
    if (typeof opts.le !== 'undefined') {
      this.le = parseInt(opts.le, 10);
      if (this.subnet.mask.length >= this.le) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.prefix_list_error_gtlen, {
          netw: this.subnet,
          len: this.subnet.mask.length,
          val: `${this.le} (le)`,
        }),
      );
    } else this.le = null;
    if (typeof opts.ge !== 'undefined') {
      this.ge = parseInt(opts.ge, 10);
      if (this.subnet.mask.length >= this.ge) throw new ValidationError(
        translatePlaceholders(window.Laravel.jsLocales.prefix_list_error_gtlen, {
          netw: this.subnet,
          len: this.subnet.mask.length,
          val: `${this.ge} (ge)`,
        }),
      );
      if (this.le !== null) {
        if (this.ge > this.le) throw new ValidationError(
          translatePlaceholders(window.Laravel.jsLocales.prefix_list_error_ge_le, {
            netw: this.subnet,
            len: this.subnet.mask.length,
            ge: this.ge,
            le: this.le,
          }),
        );
      }
    } else this.ge = null;
  }

  matches(network: Network): boolean {
    let localNetwork = network;
    if (this.ge === null && this.le === null) return this.subnet.toString() === localNetwork.toString();

    const subnetIp = this.subnet.ip.toString();
    if (this.subnet.mask.length === 0) {
      localNetwork = new Network(`${subnetIp}/${localNetwork.mask.length}`);
    }
    const from = this.le || 32;
    const to = this.ge || 0;
    for (let l = from; l >= to; l--) {
      const ip = localNetwork.mask.length === l ? localNetwork.ip : localNetwork.ip.networkAddress(new Netmask(l));
      if (subnetIp === ip.toString() && l === localNetwork.mask.length) return true;
    }

    return false;
  }
}
