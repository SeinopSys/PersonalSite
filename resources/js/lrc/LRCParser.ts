import { LRC_META_REGEX, LRC_TS_REGEX, LRCMetadata } from './common';
import { LRCString } from './LRCString';

export class LRCParser {
  public timings: LRCString[];

  public metadata: LRCMetadata;

  constructor(lrcFile: string) {
    this.timings = [];
    this.metadata = {};
    const file = lrcFile.trim();
    if (file.length === 0) throw new Error(window.Laravel.jsLocales.dialog_parse_error_empty);
    const lines = file.split('\n');
    lines.forEach(el => {
      const timestamps = el.match(LRC_TS_REGEX);
      if (timestamps) {
        const text = el.replace(LRC_TS_REGEX, '');
        timestamps.forEach(ts => {
          this.timings.push(new LRCString(text, ts.substring(1, ts.length - 1)));
        });
      } else {
        const trimmedEl = el.trim();
        const metadata = trimmedEl.match(LRC_META_REGEX);
        if (metadata) {
          const [, metadataName, metadataValue] = metadata;
          this.metadata[metadataName] = metadataName === 'length' ? metadataValue.trim() : metadataValue;
        }
      }
    });
    if (this.timings.length === 0) throw new Error(window.Laravel.jsLocales.dialog_parse_error_no_timing);
    this.timings.sort((a, b) => a.ts.seconds - b.ts.seconds);
  }
}
