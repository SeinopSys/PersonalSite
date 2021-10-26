import { pad } from '../utils';

export class IPV6AddressBlock {
  constructor(public value = 0) {
  }

  toString(padValue = false) {
    let val = this.value.toString(16).toLowerCase();
    if (padValue) val = pad(val, '0', 4);
    return val;
  }
}
