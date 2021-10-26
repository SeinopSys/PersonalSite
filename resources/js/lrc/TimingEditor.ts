import { saveAs } from 'file-saver';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore
import { Blob } from 'blob-polyfill';
import { Dialog } from '../dialog';
import { mkData, setElDisabled } from '../utils';
import type { AudioPlayer } from './AudioPlayer';
import {
  binarySearch, clearFocus, LRC_META_TAGS, LRCMetadata,
} from './common';
import { Duration } from './Duration';
import { LRCParser } from './LRCParser';
import { LRCString } from './LRCString';
import { MetadataEditingForm } from './MetadataEditingForm';

export class TimingEditor {
  protected mergedOutputStrategy: boolean;

  protected $timings: JQuery;

  protected $lrcmodebtn: JQuery;

  protected $lrcfilebtn: JQuery;

  protected $filein: JQuery<HTMLInputElement>;

  protected $lrcpastebtn: JQuery;

  protected $lrcexportbtn: JQuery;

  protected $lrcexportnometabtn: JQuery;

  protected $lrcmergetogglebtn: JQuery;

  protected $lrcclrbtn: JQuery;

  protected $lrcmetadatabtn: JQuery;

  protected $editor: JQuery;

  protected $entryTemplate: JQuery;

  protected lastLRCFilename?: string;

  protected mode: 'edit' | 'sync' = 'edit';

  protected metadata: LRCMetadata;

  protected initialMetadata: LRCMetadata;

  protected timings: LRCString[] = [];

  constructor(private pluginScope: { player: AudioPlayer }) {
    this.$timings = $('#timings');
    this.$lrcmodebtn = $('#lrcmodebtn');
    this.$lrcfilebtn = $('#lrcfilebtn');
    this.$filein = $(document.createElement('input'))
      .attr({
        id: 'lrcfilein',
        type: 'file',
        accept: '.lrc',
        tabindex: -1,
        class: 'fileinput',
      })
      .appendTo($(document.body));
    this.$lrcpastebtn = $('#lrcpastebtn');
    this.$lrcexportbtn = $('#lrcexportbtn');
    this.$lrcexportnometabtn = $('#lrcexportnometabtn');
    this.$lrcmergetogglebtn = $('#lrcmergetogglebtn');
    this.$lrcclrbtn = $('#lrcclrbtn');
    this.$lrcmetadatabtn = $('#lrcmetadatabtn');
    this.$editor = this.$timings.find('.editor');
    this.$entryTemplate = $('#editor-entry-template').children();
    this.changeMode('edit', false, true);
    this.initialMetadata = {
      offset: '0',
      re: `${window.location.href} - SeinopSys' LRC Editor`,
      ve: window.Laravel.git.commit_id,
    };
    this.metadata = {};
    // Insert a blank entry on first start
    this.$editor.append(this.makeEntryDiv(new LRCString()));

    this.lastLRCFilename = undefined;
    this.mergedOutputStrategy = TimingEditor.getMergedOutputStrategyDefault();
    this.updateMergeStrategyButton();

    this.$lrcmodebtn.on('click', e => {
      e.preventDefault();

      if (this.$lrcmodebtn.is('[disabled]')) return;

      this.changeMode(null, e.altKey);
      clearFocus();
    });
    this.$lrcpastebtn.on('click', e => {
      e.preventDefault();

      const $rawLyricsForm = $<HTMLFormElement>(document.createElement('form'))
        .attr('id', 'rawlyrics')
        .append(
          `<div class="mb-3">
            <textarea class="form-control" rows="10"></textarea>
          </div>
          <p class="text-info"><span class="fa fa-info-circle me-2"></span>${window.Laravel.jsLocales.dialog_pasteraw_info}</p>`,
        );
      Dialog.request({
        title: window.Laravel.jsLocales.dialog_pasteraw_title,
        content: $rawLyricsForm,
        confirmBtn: window.Laravel.jsLocales.dialog_pasteraw_action,
        callback: $form => {
          $form.on('submit', se => {
            se.preventDefault();

            Dialog.wait(false, 'Importing');
            const lines: string[] = ($form.find<HTMLTextAreaElement>('textarea').prop('value') || '').trim().split(/\n+/g);
            lines.push(''); // Add an empty line to account for an outro
            this.timings = lines.map(el => new LRCString(el));
            this.pluginScope.player
              .updateEntrySticks();
            this.regenEntries();
            this.lastLRCFilename = undefined;

            Dialog.close();
          });
        },
      });
    });
    this.$lrcexportbtn.on('click', e => {
      e.preventDefault();

      this.storeTimings();
      this.exportLRCFile();
    });
    this.$lrcexportnometabtn.on('click', e => {
      e.preventDefault();

      this.storeTimings();
      this.exportLRCFile(false);
    });
    this.$lrcmergetogglebtn.on('click', e => {
      e.preventDefault();

      this.toggleMergeStrategy();
    });
    this.$lrcclrbtn.on('click', e => {
      e.preventDefault();

      Dialog.confirm({
        title: $(e.target).text(),
        handlerFunc: sure => {
          if (!sure) return;

          this.$editor.empty();
          this.$editor.append(this.makeEntryDiv(new LRCString()));
          this.storeTimings();
          Dialog.close();
        },
      });
    });
    this.$lrcmetadatabtn.on('click', e => {
      e.preventDefault();

      const $form = new MetadataEditingForm(this.getCurrentMetadata()).get();
      Dialog.request({
        title: window.Laravel.jsLocales.dialog_edit_meta,
        content: $form,
        confirmBtn: window.Laravel.jsLocales.save,
        callback: () => {
          $form.on('submit', se => {
            se.preventDefault();

            const data = mkData($form);
            $.each(LRC_META_TAGS, key => {
              this.metadata[key] = (data[key] || '').trim();
            });
            Dialog.close();
          });
          $form.on('reset', re => {
            re.preventDefault();

            this.metadata = {};
            const newMetadata = this.getCurrentMetadata();
            $.each(LRC_META_TAGS, key => {
              $form.find(`input[name=${key}]`).val(newMetadata[key]);
            });
          });
        },
      });
    });
    this.$editor.on('mouseenter', () => {
      this.$editor.stop();
    });
    this.$editor.on('keyup', '.timestamp', e => {
      if (this.mode !== 'edit') return;

      const $ts = $(e.target);
      const val = $ts.text().trim();
      const valInvalid = !Duration.isValid(val);
      $ts[val.length && valInvalid ? 'addClass' : 'removeClass']('invalid');
      setElDisabled($ts.siblings('.tools').children('.goto'), valInvalid);
      this.pluginScope.player
        .updateEntrySticks();
    });
    this.$editor.on('click', '.addrow-up, .addrow-down', e => {
      e.preventDefault();

      const elClass = $(e.target)
        .closest('button')
        .attr('class') || '';
      const mathDirection = elClass.match(/addrow-(up|down)/);
      const insertWhere = mathDirection && mathDirection[1] === 'up' ? 'insertBefore' : 'insertAfter';
      const $entry = this.makeEntryDiv(new LRCString())[insertWhere]($(e.target).closest('.time-entry'));
      this.updateEntryActionButtons();
      this.storeTimings();
      $entry.addClass('new');
      this.scrollHighlightedIntoView($entry, false);
    });
    this.$editor.on('click', '.remrow', e => {
      e.preventDefault();

      const $entry = $(e.target).closest('.time-entry');
      if ($entry.siblings().length === 0) return;

      $entry.remove();
      this.updateEntryActionButtons();
      this.storeTimings();
      this.hlEntry(this.pluginScope.player.getPlaybackPosition());
    });
    this.$editor.on('click', '.goto', e => {
      e.preventDefault();

      const $button = $(e.target).closest('.goto');
      if ($button.is(':disabled')) return;

      const $entry = $button.parents('.time-entry');
      const ts = $entry.find('.timestamp').text();
      this.pluginScope.player.setPlaybackPosition(ts);

      if (this.mode === 'sync') this.passSyncHandle($entry);
      clearFocus();
    });
    this.$lrcfilebtn.on('click', e => {
      e.preventDefault();

      this.$filein.trigger('click');
      clearFocus();
    });
    this.$filein.on('change', () => {
      const val = this.$filein.val();

      if (!val) return;

      setElDisabled(this.$lrcfilebtn, true);
      this.readLRCFile(this.$filein[0].files?.[0], success => {
        if (success) this.regenEntries();
        else this.$filein.val('');
        setElDisabled(this.$lrcfilebtn, false);
      });
    });
  }

  disableModeButton(disable: boolean): void {
    setElDisabled(this.$lrcmodebtn, disable);
  }

  changeMode(mode: 'sync' | 'edit' | null = null, preservePosition = false, forceChange = false): void {
    const localMode = mode == null ? (this.mode === 'sync' ? 'edit' : 'sync') : mode;
    if (this.mode === localMode && !forceChange) return;

    const $children = this.$lrcmodebtn.children();
    const modeIcon = ({
      edit: 'edit',
      sync: 'time',
    })[localMode];
    const otherMode = localMode === 'sync' ? 'edit' : 'sync';
    const otherIcon = localMode === 'sync' ? 'edit' : 'clock';
    $children.first()
      .removeClass(`fa-${modeIcon}`)
      .addClass(`fa-${otherIcon}`)
      .next()
      .text(this.$lrcmodebtn.attr(`data-${otherMode}mode`) || '');
    this.$editor.removeClass(`mode-${otherMode}`)
      .addClass(`mode-${localMode}`);
    this.mode = localMode;

    if (this.mode === 'edit') {
      this.revokeSyncHandle();
      this.$editor.find('.text, .timestamp').attr('contenteditable', 'true');
      this.$editor.find('.sync-handle').removeClass('sync-handle');
      return;
    }

    this.$editor.find('.text, .timestamp').removeAttr('contenteditable');
    const $handle = this.getSyncHandle(preservePosition);
    if (!$handle) throw new Error('Missing handle');
    this.scrollHighlightedIntoView($handle, false);
  }

  getTimings(): LRCString[] {
    return this.timings;
  }

  setTimings(timings: LRCString[]): void {
    this.timings = timings;
    this.regenEntries();
    this.pluginScope.player.updateEntrySticks();
  }

  setInitialMetadata(metadata: Partial<LRCMetadata>): void {
    (['ar', 'ti', 'al', 'length'] as const).forEach(el => {
      const long = LRC_META_TAGS[el];
      let value = metadata[long];
      if (el === 'length' && typeof value === 'string') value = value.trim();

      if (value) this.initialMetadata[el] = value;
      else if (value === null) delete this.initialMetadata[el];
    });
  }

  setMetadata(metadata: LRCMetadata): void {
    this.metadata = metadata;
  }

  getCurrentMetadata(): Record<string, string> {
    return $.extend({}, this.initialMetadata, this.metadata);
  }

  storeTimings(): void {
    const $children = this.$editor.children();
    const timings: LRCString[] = [];
    $children.each(function () {
      const $entry = $(this);
      const ts = $entry.children('.timestamp').text().trim();
      if (!ts.length || !Duration.isValid(ts)) return;

      const text = $entry.children('.text').text().trim().split('\n');

      $.each(text, (_, el) => {
        timings.push(new LRCString(
          el,
          ts,
          $entry,
        ));
      });
    });
    this.timings = timings;
    this.pluginScope.player.updateEntrySticks();
  }

  makeEntryDiv(lrcString: LRCString): JQuery {
    const $clone = this.$entryTemplate.clone();
    $clone.children()
      .first()
      .text(lrcString.ts.toString())
      .trigger('keyup')
      .next()
      .text(lrcString.str);
    return $clone;
  }

  regenEntries(): void {
    this.$editor.empty();
    $.each(this.timings, (i, el) => {
      const $node = this.makeEntryDiv(el);
      this.timings[i].$domNode = $node;
      this.$editor.append($node);
    });
    this.passSyncHandle(this.$editor.children().first());
    this.updateEntryActionButtons();
  }

  updateEntryActionButtons(): void {
    const $entries = this.$editor.children();
    setElDisabled($entries.find('.remrow'), $entries.length === 1);
    const $toDisable = $entries.find('.goto');
    setElDisabled($toDisable, true);
    setElDisabled($toDisable.filter(function () {
      return Duration.isValid($(this).parent().siblings().first()
        .text()
        .trim());
    }), false);
  }

  readLRCFile(file: File | null | undefined, callback: (value: boolean) => void): void {
    if (!file) throw new Error('Missing file');
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const parsed = new LRCParser(reader.result as string);
        this.setTimings(parsed.timings);
        this.setMetadata(parsed.metadata);
      } catch (e) {
        Dialog.fail(window.Laravel.jsLocales.dialog_parse_error, e instanceof Error ? e.message : undefined);
        callback(false);
        return;
      }
      this.lastLRCFilename = file.name.replace(/\.lrc$/, '');
      callback(true);
    };
    reader.readAsText(file);
  }

  static giveSyncHandleTo<El extends JQuery>($el: El): El {
    $el.addClass('sync-handle');
    $el.find('.timestamp').focus();
    return $el;
  }

  getSyncHandle(nowplayingAsHandle = false): JQuery | undefined {
    if (this.mode === 'edit') return undefined;

    const $children = this.$editor.children();
    if (!$children.length) return undefined;

    let $handle = this.$editor.children('.sync-handle');
    const $nowplaying = this.$editor.children('.nowplaying')
      .removeClass('nowplaying');
    if (nowplayingAsHandle && !$handle.length) {
      $handle = $nowplaying;
      if ($handle.length) $handle.addClass('sync-handle');
    }
    if (!$handle.length) $handle = TimingEditor.giveSyncHandleTo(this.$editor.children()
      .first());
    return $handle;
  }

  revokeSyncHandle(): JQuery | undefined {
    const $current = this.getSyncHandle();
    if (typeof $current !== 'undefined') $current.removeClass('sync-handle nowplaying').removeAttr('title');

    return $current;
  }

  passSyncHandle($next: JQuery | null = null, resetOnEnd = true): void {
    if (this.mode === 'edit') return;

    const $current = this.revokeSyncHandle();
    if (!$current) throw new Error('Expected $current to be defined');

    let $localNext = $next;
    if ($localNext === null) $localNext = $current.next();

    if (!$localNext.length) {
      if (resetOnEnd) {
        this.changeMode('edit');
        return;
      }
      $localNext = $current;
    }

    TimingEditor.giveSyncHandleTo($localNext);
    this.scrollHighlightedIntoView($localNext, false);
  }

  syncEntry(writeTs = true, resetOnEnd = true): void {
    if (this.mode === 'edit') return;

    const $handle = this.getSyncHandle();
    if (!$handle) throw new Error('Missing handle');

    const pos = this.pluginScope.player.getPlaybackPosition()?.toString();

    if (writeTs) {
      const $prevTs = $handle.prev().find('.timestamp');
      if ($prevTs.text().trim() === pos) {
        $prevTs.removeClass('flash').addClass('flash');
        return;
      }

      $handle.find('.timestamp').text(pos || '').trigger('keyup');
    }
    this.passSyncHandle(null, resetOnEnd);
    this.storeTimings();
    this.updateEntryActionButtons();
    this.pluginScope.player.updateEntrySticks();
  }

  syncBreakEntry(): void {
    if (this.mode === 'edit') return;

    const $handle = this.getSyncHandle();
    if (!$handle) throw new Error('Missing handle');

    const pos = this.pluginScope.player.getPlaybackPosition();

    this.makeEntryDiv(new LRCString('', pos))
      .insertBefore($handle)
      .find('[contenteditable]')
      .removeAttr('contenteditable');
    this.scrollHighlightedIntoView($handle, false);
    this.updateEntryActionButtons();
    this.storeTimings();
    this.pluginScope.player.updateEntrySticks();
  }

  undoSync(): void {
    if (this.mode === 'edit') return;

    const $handle = this.getSyncHandle();
    if (!$handle) throw new Error('Missing handle');

    if ($handle.index() === 0) return;
    this.passSyncHandle($handle.prev());
    this.storeTimings();
    this.updateEntryActionButtons();
    this.pluginScope.player.updateEntrySticks();
  }

  emptySync(): void {
    if (this.mode === 'edit') return;

    const $handle = this.getSyncHandle();
    if ($handle) $handle.find('.timestamp').empty().trigger('keyup');
    this.passSyncHandle();
    this.storeTimings();
    this.pluginScope.player.updateEntrySticks();
  }

  hlEntry(position?: Duration): void {
    if (this.mode === 'sync' || typeof position === 'undefined') return;

    const usableTimings = this.getTimings();
    const i = binarySearch(usableTimings, n => n.ts.seconds - position.seconds);
    if (i < 0) {
      $('.nowplaying').removeClass('nowplaying');
      return;
    }

    const $hl = usableTimings[i].$domNode;
    if (!$hl || $hl.hasClass('nowplaying')) return;

    this.scrollHighlightedIntoView($hl);
  }

  scrollHighlightedIntoView($hl: JQuery, highlight = true): void {
    if (highlight) $hl.addClass('nowplaying')
      .siblings()
      .removeClass('nowplaying');

    if (this.$editor.is(':hover')) return;

    const hlpos = $hl.position();
    const edpos = this.$editor.position();
    const scrl = this.$editor.scrollTop() || 0;

    const newscrl = scrl + (hlpos.top - edpos.top) - ((this.$editor.height() || 0) / 2) + (($hl.height() || 0) / 2);
    this.$editor.stop().animate({ scrollTop: newscrl }, 400);
  }

  /**
   * Grab last value from local storage and use if available
   */
  static getMergedOutputStrategyDefault(): boolean {
    if (!window.localStorage) return true;

    let stored = localStorage.getItem('lrc-merge');
    if (!stored || stored !== 'false') {
      stored = 'true';
      localStorage.setItem('lrc-merge', stored);
    }

    return stored === 'true';
  }

  private toggleMergeStrategy() {
    this.mergedOutputStrategy = !this.mergedOutputStrategy;
    if (window.localStorage) localStorage.setItem('lrc-merge', this.mergedOutputStrategy ? 'true' : 'false');
    this.updateMergeStrategyButton();
  }

  private updateMergeStrategyButton() {
    const $status = this.$lrcmergetogglebtn.find('.status');
    if (this.mergedOutputStrategy) {
      $status.addClass('text-success')
        .removeClass('text-danger');
    } else {
      $status.removeClass('text-success')
        .addClass('text-danger');
    }
    $status.text($status.attr(`data-${this.mergedOutputStrategy ? 'true' : 'false'}`) || '');
  }

  exportLRCFile(includeMetadata = true): void {
    let outputArr: string[] = [];
    if (includeMetadata) {
      const metadata = this.getCurrentMetadata();
      $.each(metadata, (k, v) => {
        let value = v;
        if (value !== '') {
          switch (k) {
          case 'offset':
            if (value === '0') return;
            break;
          case 'length':
            value = ` ${value}`;
            break;
          }
          outputArr.push(`[${k}:${value}]`);
        }
      });
    }
    if (this.mergedOutputStrategy) {
      const strings: Record<string, string[]> = {};
      $.each(this.timings, (i, el) => {
        if (typeof strings[el.str] === 'undefined') strings[el.str] = [];

        strings[el.str].push(el.ts.toString(true));
      });
      $.each(strings, (str: string, tsArr: string[]) => {
        outputArr.push(`[${tsArr.join('][')}]${str}`);
      });
    } else {
      outputArr = [
        ...outputArr,
        ...this.timings.map(el => `[${el.ts.toString(true)}]${el.str}`),
      ];
    }
    const output = `${outputArr.join('\n')}\n`;
    const basename = this.lastLRCFilename || this.pluginScope.player.getFileName() || 'Lyrics';
    const filename = `${basename}.lrc`;

    const blob = new Blob([output], { type: 'text/plain;charset=utf-8' });
    saveAs(blob, filename);
  }
}
