import { Duration } from './Duration';

export interface LRCStringJsonValue {
  ts: string;
  str: string;
}

export class LRCString {
  public str: string;

  public ts: Duration;

  public $domNode: JQuery | null;

  constructor(input: string | LRCStringJsonValue = '', ts: Duration | string | null = null, domNode: JQuery | null = null) {
    this.$domNode = domNode;
    if (typeof input !== 'string') {
      this.str = input.str;
      this.ts = new Duration(input.ts);
    } else {
      this.str = input.trim();
      this.ts = ts instanceof Duration ? ts : new Duration(ts);
    }
  }

  toJsonValue(): LRCStringJsonValue {
    return {
      ts: this.ts.toString(),
      str: this.str,
    };
  }

  static isValidJsonData(item: unknown): item is LRCStringJsonValue {
    return (
      typeof item === 'object'
      && item !== null
      && 'ts' in item
      && typeof item.ts === 'string'
      && 'str' in item
      && typeof item.str === 'string'
    );
  }
}
