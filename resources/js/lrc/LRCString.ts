import { Duration } from './Duration';

export class LRCString {
  public str: string;

  public ts: Duration;

  public $domNode: JQuery | null;

  constructor(str = '', ts: Duration | string | null = null, domNode: JQuery | null = null) {
    this.str = str.trim();
    this.ts = ts instanceof Duration ? ts : new Duration(ts);
    this.$domNode = domNode;
  }
}
