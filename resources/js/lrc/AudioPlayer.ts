// eslint-disable-next-line import/no-unresolved
import { throttle } from 'throttle-debounce';
import MP3Tag, { MP3Buffer } from 'mp3tag.js';
import type { MP3TagTags } from 'mp3tag.js/types/tags';
import { Reader as JsMediaTagsReader } from 'jsmediatags';
import type { Tags as JsMediaTagsTags } from 'jsmediatags/types';
import { Dialog } from '../dialog';
import {
  pad, rangeLimit, roundTo, setElDisabled,
} from '../utils';
import { AudioPlaybackIndicator } from './AudioPlaybackIndicator';
import { clearFocus, LRCMetadata, MinimalMediaTags } from './common';
import { Duration } from './Duration';
import type { TimingEditor } from './TimingEditor';

export class AudioPlayer {
  private $player: JQuery;

  private $filein: JQuery<HTMLInputElement>;

  private readonly $audiofilebtn: JQuery;

  private audio: HTMLAudioElement;

  private mediaTags: Partial<Record<MinimalMediaTags, string>>;

  private volumeStep = 0.05;

  private $volumedisp: JQuery;

  public $playbackbtn: JQuery;

  public $stopbtn: JQuery;

  public $volumedown: JQuery;

  public $volumeup: JQuery;

  private updateIndTimer?: ReturnType<typeof setTimeout>;

  private stateInd: AudioPlaybackIndicator;

  private file: File | undefined;

  private mp3TagInstance: MP3Tag | undefined;

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

    this.mediaTags = {};
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
    this.$filein.on('change', async () => {
      const val = this.$filein.val();

      if (!val) return;

      this.clearFile();
      setElDisabled(this.$audiofilebtn, true);
      try {
        this.file = await this.setFile(this.$filein[0].files?.[0]);
        this.stateInd.setFileName(this.file.name, this.file.type);
      } catch (e) {
        console.error(e);
        this.clearFile();
        setElDisabled(this.$audiofilebtn, true);
      }
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

  async setFile(file?: File | null): Promise<File> {
    this.stop();
    this.stateInd.showThumb(false);
    this.updatePlaybackButtons();
    if (!file || !/^(?:audio\/|video\/ogg)/.test(file.type)) {
      Dialog.fail(window.Laravel.jsLocales.dialog_format_error, window.Laravel.jsLocales.dialog_format_notaudio);
      return Promise.reject(new Error(`[setFile] Audio file expected, got ${file?.type}`));
    }
    this.audio.src = URL.createObjectURL(file);
    try {
      console.info('[setFile] First attempt using using mp3tag.js');
      const tags = await this.readMp3Metadata(file);
      this.setMp3MediaTags(tags);
      return file;
    } catch (e) {
      console.error(e);
    }

    console.info(`[setFile] File type is ${file.type}, falling back to jsmediatags`);
    const tags = await this.readAudioMetadata(file);
    this.setAudioMediaTags(tags);
    return file;
  }

  private async readAudioMetadata(file: File): Promise<JsMediaTagsTags> {
    return new Promise((res, rej) => {
      new JsMediaTagsReader(file)
        .setTagsToRead(['title', 'artist', 'album'])
        .read({
          onSuccess: data => {
            if (typeof data === 'object') {
              res(data.tags);
            }
          },
          onError(error) {
            console.log('Failed to read ID3 tags', error.type, error.info);
            rej(error);
          },
        });
    });
  }

  private readMp3Metadata(file: File): Promise<MP3TagTags> {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => {
        const buffer = reader.result as MP3Buffer;

        // MP3Tag Usage
        this.mp3TagInstance = new MP3Tag(buffer);
        this.mp3TagInstance.read();

        // Handle error if there's any
        if (this.mp3TagInstance.error !== '') {
          reject(new Error(this.mp3TagInstance.error));
          return;
        }

        resolve(this.mp3TagInstance.tags);
      };

      reader.readAsArrayBuffer(file);
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
    this.file = undefined;
    this.clearMediaTags();
  }

  getFileName(): string {
    const tags = this.mediaTags;
    if (tags?.title) {
      if (tags.artist) {
        return `${tags.artist} - ${tags.title}`;
      }
      return tags.title;
    }
    return this.stateInd.getFileName();
  }

  getFileExtension(): string {
    let extension = this.file?.name.replace(/^.*\.([^.]+)$/, '$1');
    if (extension) {
      return extension;
    }
    const fileType = this.file?.type;
    if (fileType && fileType.includes('/')) {
      extension = fileType.split('/').pop()?.toLowerCase();
    }

    return typeof extension === 'string' ? extension : 'bin';
  }

  getMp3TagInstance(): MP3Tag | undefined {
    return this.mp3TagInstance;
  }

  clearMediaTags(): void {
    this.mediaTags = {};
    this.mp3TagInstance = undefined;
    this.pluginScope.editor.setInitialMetadata({ length: undefined });
  }

  setMp3MediaTags(tags?: MP3TagTags): void {
    if (typeof tags !== 'object') throw new Error(`setMp3MediaTags: tags must be an object, ${typeof tags} given.`);

    this.mediaTags = {
      album: tags?.album,
      artist: tags?.artist,
      title: tags?.title,
      lyrics: tags?.v2?.USLT?.[0]?.text,
    };
    this.resetInitialMetadata();
  }

  setAudioMediaTags(tags?: JsMediaTagsTags): void {
    if (typeof tags !== 'object') throw new Error(`setAudioMediaTags: tags must be an object, ${typeof tags} given.`);

    this.mediaTags = {
      album: tags?.album,
      artist: tags?.artist,
      title: tags?.title,
      lyrics: tags?.lyrics,
    };
    this.resetInitialMetadata();
  }

  resetInitialMetadata() {
    this.pluginScope.editor.setInitialMetadata(this.convertMediaTags());
  }

  private convertMediaTags(): Partial<LRCMetadata> {
    return {
      al: this.mediaTags?.album,
      ar: this.mediaTags?.artist,
      ti: this.mediaTags?.title,
      lyrics: this.mediaTags?.lyrics,
    };
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
