import { ipversion } from './common';
import { IPV4Address } from './IPV4Address';
import { IPV6Address } from './IPV6Address';
import { Netmask } from './Netmask';

export class Network {
  public ip: IPV6Address | IPV4Address;

  public mask: Netmask;

  constructor(cidr: string) {
    const _split = cidr.split('/');
    const
      ip = _split[0].trim();
    const ipv6 = ipversion(cidr) === '6';
    this.mask = new Netmask(_split[1].trim(), false, ipv6);
    this.ip = (ipv6 ? new IPV6Address(ip) : new IPV4Address(ip)).networkAddress(this.mask);
  }

  toString() {
    return `${this.ip}/${this.mask}`;
  }

  setMask(to: string | number, ipv6 = false) {
    this.mask = new Netmask(to, false, ipv6);
    this.ip = this.ip.networkAddress(this.mask);

    return this;
  }
}
