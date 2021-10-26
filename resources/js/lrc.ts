/* eslint-disable no-use-before-define */
// eslint-disable-next-line max-classes-per-file
import { throttle } from 'throttle-debounce';
import { Dialog } from './dialog';
import { Key } from './utils/Key';
import { AudioPlayer } from './lrc/AudioPlayer';
import { PluginScope } from './lrc/PluginScope';
import { TimingEditor } from './lrc/TimingEditor';

const pluginScope: PluginScope = {} as PluginScope;

pluginScope.player = new AudioPlayer(pluginScope);
pluginScope.editor = new TimingEditor(pluginScope);

$(document).on('keydown', throttle(200, e => {
  const tagName = e.target.tagName.toLowerCase();
  if (tagName === 'input' && e.target.type !== 'file') return;
  if (tagName === 'textarea' || e.target.getAttribute('contenteditable') !== null) return;

  switch (e.keyCode) {
  case Key.LeftArrow:
    pluginScope.player.seek(-2.5 * (e.shiftKey ? 2 : 1));
    break;
  case Key.RightArrow:
    pluginScope.player.seek(2.5 * (e.shiftKey ? 2 : 1));
    break;
  case Key.PageUp:
    pluginScope.player.$volumeup.trigger('click', [e.shiftKey, e.altKey]);
    break;
  case Key.PageDown:
    pluginScope.player.$volumedown.trigger('click', [e.shiftKey, e.altKey]);
    break;
  case Key.Space:
    pluginScope.player.$playbackbtn.trigger('click');
    break;
  case Key.Period:
    pluginScope.player.$stopbtn.trigger('click');
    break;
  case Key.Enter:
    e.preventDefault();
    if (e.ctrlKey && !e.altKey) {
      pluginScope.editor.syncBreakEntry();
    } else {
      pluginScope.editor.syncEntry();
    }
    break;
  case Key.UpArrow:
    pluginScope.editor.undoSync();
    break;
  case Key.DownArrow:
    pluginScope.editor.syncEntry(false, false);
    break;
  case Key.Delete:
    pluginScope.editor.emptySync();
    break;
  default:
    return;
  }

  e.preventDefault();
}));

const isClipboardEvent = (e: unknown): e is ClipboardEvent => typeof e === 'object'
  && e !== null
  && 'clipboardData' in e
  && Boolean((e as { clipboardData: ClipboardEvent }).clipboardData);

// http://stackoverflow.com/a/17545260/1344955
$(document.body).on('paste', '[contenteditable]', function (e) {
  let text: string | JQuery = '';
  const $this = $(this);

  if (isClipboardEvent(e) && e.clipboardData) text = e.clipboardData.getData('text/plain');
  else if ('clipboardData' in window) text = (window as unknown as { clipboardData: DataTransfer }).clipboardData.getData('Text');
  else if (isClipboardEvent(e.originalEvent) && e.originalEvent.clipboardData) {
    text = $(document.createElement('div')).text(e.originalEvent.clipboardData.getData('text'));
  }

  if (document.queryCommandSupported('insertText')) {
    document.execCommand('insertHTML', false, typeof text === 'string' ? text : $(text).html());
    e.preventDefault();
    e.stopPropagation();
    return;
  }
  $this.find('*').each(function () {
    $(this).addClass('within');
  });

  setTimeout(() => {
    $this.find('*')
      .each(function () {
        $(this)
          .not('.within')
          .contents()
          .unwrap();
      });
  }, 1);
})
  .on('keyup', '[contenteditable]', function () {
    const $this = $(this);
    if ($this.text().trim().length === 0) $this.empty();
  });

const $ist = $('#info-shortcuts-template');
if ('userAgent' in navigator && /(macos|iphone|os ?x|ip[ao]d)/i.test(navigator.userAgent)) {
  $ist.find('kbd:contains(Shift)').html('&#x2325;');
  $ist.find('kbd:contains(Ctrl)').html('&#x2318;');
}
$('#shortcut-info')
  .on('click', function (e) {
    e.preventDefault();

    const title = $(this).attr('title') as string;
    Dialog.info(title, $ist.clone()
      .children());
  });

Object.assign(window, { Plugin: pluginScope });
