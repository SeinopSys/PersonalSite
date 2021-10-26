import { Subnet } from './Subnet';

export class SubnetList {
  public networks: Subnet[];

  constructor(netArray: string[], ipv6: boolean) {
    this.networks = [];
    $.each(netArray, (_, el) => {
      const sn = new Subnet(el, ipv6);
      if (sn.empty) return;
      this.networks.push(sn);
    });
  }
}
