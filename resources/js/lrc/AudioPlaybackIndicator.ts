import { floatToPercent } from './common';
import { Duration } from './Duration';
import { LRCString } from './LRCString';

export class AudioPlaybackIndicator {
  private $position: JQuery;

  private $duration: JQuery;

  private $filename: JQuery;

  private $filetype: JQuery;

  private $progressFill: JQuery;

  private $progressLoaded: JQuery;

  private $entrySticks: JQuery;

  private $thumb: JQuery;

  public $progressWrap: JQuery;

  constructor(protected $stateDiv: JQuery) {
    this.$position = this.$stateDiv.find('.status-position');
    this.$duration = this.$stateDiv.find('.status-duration');
    this.$filename = this.$stateDiv.find('.status-filename');
    this.$filetype = this.$stateDiv.find('.status-filetype');
    this.$progressWrap = this.$stateDiv.find('.progress-wrap');
    this.$progressFill = this.$stateDiv.find('.progress-indicator .fill');
    this.$progressLoaded = this.$stateDiv.find('.progress-indicator .loaded');
    this.$entrySticks = this.$stateDiv.find('.progress-indicator .entry-sticks');
    this.$thumb = this.$stateDiv.find('.thumb');
    this.setFileName();
    this.setProgress();
    this.showThumb();
  }

  setFileName(name?: string, type?: string): void {
    this.$filename.text(name ? name.replace(/\.[^.]+$/, '') : '');
    this.$filetype.text(type ? type.split('/')[1].toUpperCase() : '');
  }

  getFileName(): string {
    return this.$filename.text().trim();
  }

  setProgress(position?: number, duration?: number): void {
    if (position === undefined && duration === undefined) {
      this.$position.html('&hellip;');
      this.$duration.html('&hellip;');
      return;
    }
    const pos = position || 0;
    const dur = duration || 0;
    this.$position.text(new Duration(pos).toString());
    this.$duration.text(new Duration(dur).toString());
    this.updateSeek(pos, dur);
  }

  setLoaded(timeRanges: TimeRanges, duration: number): void {
    this.$progressLoaded.empty();
    for (let i = 0; i < timeRanges.length; i++) {
      const start = timeRanges.start(i);
      const end = timeRanges.end(i);
      this.$progressLoaded.append($(document.createElement('div'))
        .css({
          left: floatToPercent(start / duration),
          width: floatToPercent((end - start) / duration),
        }));
    }
  }

  setEntries(entries: LRCString[], duration: number): void {
    this.$entrySticks.empty();
    $.each(entries, (_, el) => {
      this.$entrySticks.append($(document.createElement('div'))
        .css({
          left: floatToPercent(el.ts.seconds / duration),
        }));
    });
  }

  updateSeek(pos: number, dur: number): void {
    const perc = floatToPercent(pos / dur);
    this.$progressFill.css('width', perc);
    this.$thumb.css('left', perc);
  }

  showThumb(isPlaying = false): void {
    this.$stateDiv[isPlaying ? 'addClass' : 'removeClass']('playing');
  }
}
