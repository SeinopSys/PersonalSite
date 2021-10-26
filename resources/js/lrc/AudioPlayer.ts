import { Reader } from 'jsmediatags';
// eslint-disable-next-line import/no-unresolved
import type { TagType } from 'jsmediatags/types';
import { throttle } from 'throttle-debounce';
import { Dialog } from '../dialog';
import {
  callCallback,
  pad, rangeLimit, roundTo, setElDisabled,
} from '../utils';
import { AudioPlaybackIndicator } from './AudioPlaybackIndicator';
import { clearFocus } from './common';
import { Duration } from './Duration';
import type { TimingEditor } from './TimingEditor';

export class AudioPlayer {
  private $player: JQuery;

  private $filein: JQuery<HTMLInputElement>;

  private readonly $audiofilebtn: JQuery;

  private audio: HTMLAudioElement;

  private mediatags: Record<string, string>;

  private volumeStep = 0.05;

  private $volumedisp: JQuery;

  public $playbackbtn: JQuery;

  public $stopbtn: JQuery;

  public $volumedown: JQuery;

  public $volumeup: JQuery;

  private updateIndTimer?: ReturnType<typeof setTimeout>;

  private stateInd: AudioPlaybackIndicator;

  constructor(private pluginScope: { editor: TimingEditor }) {
    this.$player = $('#player');
    this.$filein = $(document.createElement('input'))
      .attr({
        id: 'audiofilein',
        type: 'file',
        accept: 'audio/*',
        tabindex: -1,
        class: 'fileinput',
      })
      .appendTo($(document.body));

    this.$audiofilebtn = $('#audiofilebtn');
    this.$playbackbtn = $('#playbackbtn');
    this.audio = new Audio();
    this.audio.preload = 'auto';
    this.$stopbtn = $('#stopbtn');
    $(this.audio)
      .on('ended', () => {
        this.stop();
        this.updatePlaybackButtons();
      })
      .on('loadeddata', () => {
        this.stateInd.setProgress();
        this.updateInd();
        setElDisabled(this.$audiofilebtn, false);
        this.updatePlaybackButtons();
        this.stateInd.showThumb(true);
        this.updateEntrySticks();
        this.setMetadataLength();
      });

    this.mediatags = {};
    this.$volumedisp = $('#volumedisp');
    this.$volumedown = $('#volumedown');
    this.$volumeup = $('#volumeup');
    const sessionVol = AudioPlayer.getSessionVolume();
    this.volume(sessionVol);
    this.updateVolumeButtons(sessionVol);

    this.stateInd = new AudioPlaybackIndicator(this.$player.find('.state'));
    let resumeOnRelease: boolean;
    this.stateInd.$progressWrap.on('mousedown', e => {
      if (e.button !== 0 || !this.hasFile()) return;

      e.preventDefault();

      this.stateInd.$progressWrap.addClass('moving');
      resumeOnRelease = !this.audio.paused;
      this.pause();
      this.updatePlaybackButtons();
    });
    $(document).on('mouseup', e => {
      if (e.button !== 0 || !this.stateInd.$progressWrap.hasClass('moving') || !this.hasFile()) return;

      this.recalcThumbPos(e);
      this.stateInd.$progressWrap.removeClass('moving');
      if (resumeOnRelease) {
        this.play();
        this.updatePlaybackButtons();
      }
    });
    $(document).on('mousemove', throttle(100, e => {
      if (!this.stateInd.$progressWrap.hasClass('moving') || !this.hasFile()) return;

      this.recalcThumbPos(e);
    }));

    this.$stopbtn.on('click', e => {
      e.preventDefault();

      if (!this.hasFile()) return;

      this.stop();
      this.updatePlaybackButtons();
      clearFocus();
    });
    this.$playbackbtn.on('click', e => {
      e.preventDefault();

      if (!this.hasFile()) return;

      if (this.audio.paused) this.play();
      else this.pause();
      this.updatePlaybackButtons();
      clearFocus();
    });
    this.$volumedown.on('click', (e, shiftKey, altKey) => {
      e.preventDefault();

      const newvol = this.volume() - (e.altKey || altKey ? 0.01 : this.volumeStep * (e.shiftKey || shiftKey ? 2 : 1));
      this.updateVolumeButtons(newvol);
      this.volume(newvol);
      clearFocus();
    });
    this.$volumeup.on('click', (e, shiftKey, altKey) => {
      e.preventDefault();

      const newvol = this.volume() + (e.altKey || altKey ? 0.01 : this.volumeStep * (e.shiftKey || shiftKey ? 2 : 1));
      this.updateVolumeButtons(newvol);
      this.volume(newvol);
      clearFocus();
    });
    this.$audiofilebtn.on('click', e => {
      e.preventDefault();

      this.$filein.trigger('click');
      clearFocus();
    });
    this.$filein.on('change', () => {
      const val = this.$filein.val();

      if (!val) return;

      this.clearFile();
      setElDisabled(this.$audiofilebtn, true);
      this.setFile(this.$filein[0].files?.[0], file => {
        this.stateInd.setFileName(file.name, file.type);
      });
    });
  }

  static getSessionVolume(): number {
    if (!window.localStorage) return 0.5;

    let stored = localStorage.getItem('lrc-vol');
    if (!stored || Number.isNaN(stored)) {
      stored = '0.5';
      localStorage.setItem('lrc-vol', stored);
    }

    return parseFloat(stored);
  }

  volume(float?: undefined): number;

  volume(float: number): void;

  // eslint-disable-next-line consistent-return
  volume(float?: number): number | void {
    if (typeof float === 'undefined') return this.audio.volume;

    const localFloat = rangeLimit(roundTo(float, 2), false, 0, 1);
    this.$volumedisp.text(`${pad(Math.round(localFloat * 100), ' ', 3)}%`);
    if (window.localStorage) localStorage.setItem('lrc-vol', String(localFloat));
    this.audio.volume = float;
  }

  updateVolumeButtons(newVol: number): void {
    setElDisabled(this.$volumeup, newVol >= 1);
    setElDisabled(this.$volumedown, newVol <= 0);
  }

  updatePlaybackButtons(): void {
    const fileMissing = !this.hasFile();
    setElDisabled(this.$playbackbtn, fileMissing);
    setElDisabled(this.$stopbtn, fileMissing);
    this.pluginScope.editor.disableModeButton(fileMissing);
    this.$playbackbtn.children().removeClass('fa-play fa-pause');
    if (!this.audio.paused) this.$playbackbtn.children().addClass('fa-pause');
    else this.$playbackbtn.children().addClass('fa-play');
  }

  play(): void {
    if (!this.audio.paused) return;

    void this.audio.play();
    this.startUpdateIndTimer();
    this.stateInd.showThumb(true);
  }

  pause(skipIndUpd = false): void {
    this.stopUpdateIndTimer();

    if (this.audio.paused) return;
    this.audio.pause();
    if (!skipIndUpd) this.updateInd();
  }

  stop(): void {
    this.pause(true);
    this.audio.currentTime = 0;
    this.updateInd();
  }

  seek(seconds: number): void {
    if (!this.hasFile()) return;

    this.audio.currentTime = rangeLimit(this.audio.currentTime + seconds, false, 0, this.audio.duration);
    this.updateInd();
  }

  setFile(file?: File | null, callback?: (f: File) => void): void {
    this.stop();
    this.stateInd.showThumb(false);
    this.updatePlaybackButtons();
    if (!file || !/^audio\//.test(file.type)) {
      Dialog.fail(window.Laravel.jsLocales.dialog_format_error, window.Laravel.jsLocales.dialog_format_notaudio);
      return;
    }
    this.audio.src = URL.createObjectURL(file);
    new Reader(file)
      .setTagsToRead(['title', 'artist', 'album'])
      .read({
        onSuccess: data => {
          if (typeof data === 'object') {
            this.setMediaTags(data.tags);
          }
        },
        onError(error) {
          console.log('Failed to read ID3 tags', error.type, error.info);
        },
      });
    callCallback({
      func: callback,
      params: [file],
    });
  }

  hasFile(): boolean {
    return this.audio.readyState !== 0;
  }

  clearFile(): void {
    this.stop();
    if (this.audio.src) {
      URL.revokeObjectURL(this.audio.src);
    }
    this.audio.src = '';
    this.clearMediaTags();
  }

  getFileName(): string {
    const tags = this.mediatags;
    if (tags.title) {
      if (tags.artist) {
        return `${tags.artist} - ${tags.title}`;
      }
      return tags.title;
    }
    return this.stateInd.getFileName();
  }

  clearMediaTags(): void {
    this.mediatags = {};
    this.pluginScope.editor.setInitialMetadata({ length: undefined });
  }

  setMediaTags(tags?: TagType['tags']): void {
    if (typeof tags !== 'object') throw new Error(`setMediaTags: tags must be an object, ${typeof tags} given.`);

    this.mediatags = Object.keys(tags).reduce((acc, key) => {
      const val = tags[key as keyof TagType['tags']];
      if (typeof val === 'string') {
        return {
          ...acc,
          [key]: val,
        };
      }
      return acc;
    }, {} as Record<string, string>);
    this.pluginScope.editor.setInitialMetadata(this.mediatags);
  }

  private startUpdateIndTimer() {
    this.stopUpdateIndTimer();
    this.updateIndTimer = setInterval(() => {
      this.updateInd();
    }, 85);
  }

  private stopUpdateIndTimer() {
    if (typeof this.updateIndTimer === 'undefined') return;

    clearInterval(this.updateIndTimer);
    this.updateIndTimer = undefined;
  }

  updateInd(): void {
    this.stateInd.setProgress(this.audio.currentTime, this.audio.duration);
    this.stateInd.setLoaded(this.audio.buffered, this.audio.duration);
    this.pluginScope.editor.hlEntry(this.getPlaybackPosition());
  }

  updateEntrySticks(): void {
    if (!this.hasFile()) return;

    this.stateInd.setEntries(this.pluginScope.editor.getTimings(), this.audio.duration);
  }

  setMetadataLength(): void {
    this.pluginScope.editor.setInitialMetadata({ length: new Duration(this.audio.duration, true).toString() });
  }

  getPlaybackPosition(): Duration | undefined {
    if (!this.hasFile()) return undefined;

    return new Duration(this.audio.currentTime);
  }

  setPlaybackPosition(ts: string): void {
    if (!this.hasFile() || !Duration.isValid(ts)) return;

    this.audio.currentTime = new Duration(ts).seconds;
    this.updateInd();
  }

  recalcThumbPos(e: { clientX: number }): void {
    const pwOffset = this.stateInd.$progressWrap.offset() || { left: 0 };
    const pwWidth = parseInt(this.stateInd.$progressWrap.css('width'), 10);
    const relativeX = rangeLimit(e.clientX - pwOffset.left, false, 0, pwWidth - 0.00001);

    this.audio.currentTime = this.audio.duration * (relativeX / pwWidth);
    this.stateInd.updateSeek(relativeX, pwWidth);
    this.updateInd();
  }
}
