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

    this.setSeconds(this.ignoreMs ? Math.ceil(seconds) : roundTo(seconds, LRC_TS_DECIMALS));
    this.valid = true;
  }

  /**
   * Guard against negative values
   */
  private setSeconds(value: number) {
    this.seconds = Number.isNaN(value) || !Number.isFinite(value) ? NaN : Math.max(value, 0);
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
      this.setSeconds(NaN);
      return;
    }
    const parts = ts.split(':');
    let dur = parseFloat(parts.pop() as string);
    switch (parts.length) {
    case 2:
      dur += parseInt(parts.pop() as string, 10) * 60;
      break;
    case 3:
      dur += parseInt(parts.pop() as string, 10) * 60;
      dur += parseInt(parts.pop() as string, 10) * 60 * 60;
      break;
    }
    this.setSeconds(dur);
    this.valid = true;
  }

  static isValid(ts: string | null): ts is string {
    if (ts === null) return false;
    if (/^(?:\d+:)?[0-5]?\d:[0-5]?\d(?:\.\d+)?$/.test(ts)) {
      return true;
    }
    const value = parseFloat(ts);
    return Number.isFinite(value) && !Number.isNaN(value) && value < 60;
  }
}
