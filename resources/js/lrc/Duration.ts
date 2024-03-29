import { pad, roundTo } from '../utils';
import { LRC_TS_DECIMALS } from './common';

export class Duration {
  public seconds = NaN;

  public valid = false;

  constructor(seconds: string | number | null, public ignoreMs = false) {
    if (seconds == null) return;

    if (typeof seconds === 'string') {
      this.fromString(seconds);
      return;
    }

    this.seconds = this.ignoreMs ? Math.ceil(seconds) : roundTo(seconds, LRC_TS_DECIMALS);
    this.valid = true;
  }

  toString(padMinutes = false): string {
    if (!this.valid) return '';
    let time = this.seconds;
    const mins = Math.floor(time / 60);
    if (mins > 0) time -= mins * 60;
    const minsStr = padMinutes ? pad(mins) : String(mins);

    const [secsStr, msStr] = time.toFixed(LRC_TS_DECIMALS).split('.');
    return `${minsStr}:${pad(secsStr)}${this.ignoreMs ? '' : `.${msStr}`}`;
  }

  private fromString(ts: string | null) {
    if (!Duration.isValid(ts)) {
      this.seconds = NaN;
      return;
    }
    const parts = ts.split(':');
    let dur = parseFloat(parts.pop() as string);
    if (parts.length > 0) dur += parseInt(parts.pop() as string, 10) * 60;
    this.seconds = dur;
    this.valid = true;
  }

  static isValid(ts: string | null): ts is string {
    if (ts === null) return false;
    return /(?:\d+:)?[0-5]?\d:[0-5]?\d(?:\.\d+)?/.test(ts);
  }
}
