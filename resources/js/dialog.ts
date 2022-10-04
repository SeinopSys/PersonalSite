import type { Modal } from 'bootstrap';
import { isKey, Key } from './utils/Key';
import { Time } from './utils/Time';
import { callCallback, capitalize, setElDisabled } from './utils';
import ClickEvent = JQuery.ClickEvent;

type DialogType = 'fail' | 'success' | 'wait' | 'request' | 'confirm' | 'info';
type ClickEventHandler = (e: ClickEvent) => void;
type DialogCallback = ($el?: JQuery) => void;
type FormDialogCallback = ($form: JQuery) => void;
export type ConfirmHandleFunction = (confirm: boolean) => void;
type ButtonsRecord = Record<string, ClickEventHandler | { action?: ClickEventHandler, form?: string, submit?: boolean }>;

interface DialogParams {
  type: DialogType;
  title: string | false;
  content: string | JQuery;
  buttons?: ButtonsRecord | boolean;
  color?: string;
}

const colors: Record<DialogType, string> = {
  fail: 'danger',
  success: 'success',
  wait: 'info',
  request: 'primary',
  confirm: 'warning',
  info: 'info',
};
const noticeClasses: Record<DialogType, string> = {
  fail: 'danger',
  success: 'success',
  wait: 'info',
  request: 'primary',
  confirm: 'warning',
  info: 'info',
};
const yiq: Record<string, 'white' | 'dark'> = {
  danger: 'white',
  success: 'white',
  info: 'white',
  primary: 'white',
  warning: 'dark',
};
const {
  defaultTitles,
  defaultContent,
} = window.Laravel.dialog as unknown as Record<string, Record<string, string>>;
const ModalClass = window.bootstrap.Modal;

const $modalElement = $(document.createElement('div')).attr({
  class: 'modal fade',
  tabindex: -1,
  role: 'dialog',
  'aria-labelledby': 'dialogHeader',
  id: 'dialogOverlay',
}).html(
  `<div class="modal-dialog" role="document" id="dialogBox">
        <div class="modal-content">
          <div class="modal-header">
          <h4 class="modal-title" id="dialogHeader"></h4>
        </div>
        <div class="modal-body" id="dialogContent"></div>
        <div class="modal-footer" id="dialogButtons"></div>
      </div>
  </div>`,
);

interface DialogDisplayOptions<T extends DialogParams['type']> {
  type: T,
  title?: DialogParams['title'],
  content?: DialogParams['content'],
  buttons?: ButtonsRecord,
  callback?: (T extends 'request' ? FormDialogCallback : DialogCallback),
  forceNew?: boolean,
}

interface DialogRequestOptions {
  title: string,
  content: JQuery | string,
  confirmBtn?: false | string,
  callback?: FormDialogCallback,
}

interface DialogConfirmOptions {
  title: string,
  content?: DialogParams['content'],
  btnTextArray?: [string, string],
  handlerFunc?: ConfirmHandleFunction,
}

class DialogManager {
  private open?: {
    type?: DialogParams['type'];
    title?: DialogParams['title'];
    content?: DialogParams['content'];
  };

  private closeButton: Record<string, VoidFunction> = {};

  private modalInstance?: Modal;

  private $focusedElement?: JQuery;

  public $dialogOverlay: JQuery;

  public $dialogContent: JQuery;

  public $dialogHeader: JQuery;

  public $dialogBox: JQuery;

  public $dialogButtons: JQuery;

  constructor() {
    this.$dialogOverlay = $('#dialogOverlay');
    this.$dialogContent = $('#dialogContent');
    this.$dialogHeader = $('#dialogHeader');
    this.$dialogBox = $('#dialogBox');
    this.$dialogButtons = $('#dialogButtons');
    this.open = this.$dialogContent.length ? {} : undefined;
    this.closeButton = {
      [window.Laravel.dialog.close]: () => {
        this.close();
      },
    };
    this.$focusedElement = undefined;
    this.modalInstance = undefined;
  }

  isOpen() {
    return typeof this.open === 'object';
  }

  private display<T extends DialogParams['type']>({
    type,
    title,
    content,
    buttons,
    callback,
    forceNew,
  }: DialogDisplayOptions<T>): void {
    let localTitle = title;
    let localContent = content;

    if (typeof colors[type] === 'undefined') throw new TypeError(`Invalid dialog type: ${typeof type}`);

    if (typeof localTitle === 'undefined') localTitle = defaultTitles[type];
    else if (localTitle === false) localTitle = '';
    if (!localContent) localContent = defaultContent[type];
    const params: DialogParams = {
      type,
      title: localTitle,
      content: localContent || defaultContent[type],
      buttons,
      color: colors[type],
    };

    const append = Boolean(this.open);
    const $contentAdd = $(document.createElement('div')).append(params.content);
    const appendingToRequest = append && this.open?.type === 'request' && ['fail', 'wait'].includes(params.type) && !forceNew;
    let $requestContentDiv: JQuery | undefined;

    if (!append) {
      this.storeFocus();
      $modalElement.clone().appendTo($(document.body));
    }
    if (appendingToRequest) {
      $requestContentDiv = this.$dialogContent.children(':not(#dialogButtons)').last();
      let $errorNotice = $requestContentDiv.children('.notice:last-child');
      if (!$errorNotice.length) {
        $errorNotice = $(document.createElement('div')).append($(document.createElement('p')).attr('class', 'mb-0'));
        $requestContentDiv.append($errorNotice);
      } else $errorNotice.show();
      $errorNotice
        .attr('class', `alert alert-${noticeClasses[params.type]}`)
        .children('p').empty().append(params.content)
        .show();
      this.controlInputs(params.type === 'wait');
    } else {
      this.open = params;
      this.$dialogOverlay = $('#dialogOverlay');
      this.$dialogContent = $('#dialogContent');
      this.$dialogHeader = $('#dialogHeader');
      if (typeof params.title === 'string') this.$dialogHeader.html(params.title);
      this.$dialogBox = $('#dialogBox');
      this.$dialogButtons = $('#dialogButtons').empty();
      this.controlInputs(true);
      this.$dialogContent.append($contentAdd);

      this.$dialogButtons[params.buttons ? 'show' : 'hide']();
    }

    if (!appendingToRequest) {
      this.$dialogHeader.parent()
        .attr('class', `modal-header${params.color ? ` bg-${params.color} text-${yiq[params.color]}` : ''}`);
    }

    if (!appendingToRequest && params.buttons && typeof params.buttons !== 'boolean') $.each(params.buttons, (name, obj) => {
      const $button = $(document.createElement('button')).attr({
        type: 'button',
        class: `btn btn-${params.color}`,
      });
      const clickHandler = obj;
      if (typeof obj !== 'function' && 'form' in obj) {
        const $contentDiv = $(`#${obj.form}`);
        $requestContentDiv = $contentDiv;
        if ($contentDiv.length === 1) {
          $button.on('click', () => {
            $contentDiv.find('input[type=submit]').first().trigger('click');
          });
          $contentDiv.prepend($(document.createElement('input')).attr('type', 'submit').hide());
        }
      }
      $button.text(name).on('keydown', e => {
        if ([Key.Enter, Key.Space].includes(e.keyCode)) {
          e.preventDefault();

          $button.trigger('click');
        } else if ([Key.Tab, Key.LeftArrow, Key.RightArrow].includes(e.keyCode)) {
          e.preventDefault();

          const $dBc = this.$dialogButtons.children();
          const $focused = $dBc.filter(':focus');
          const $inputs = this.$dialogContent.find(':input');

          if (isKey(Key.LeftArrow, e)) e.shiftKey = true;

          if ($focused.length) {
            if (!e.shiftKey) {
              if ($focused.next().length) $focused.next().focus();
              else if (isKey(Key.Tab, e)) $inputs.add($dBc).filter(':visible').first().focus();
            } else if ($focused.prev().length) $focused.prev().focus();
            else if (isKey(Key.Tab, e)) ($inputs.length > 0 ? $inputs : $dBc).filter(':visible').last().focus();
          } else $inputs.add($dBc)[!e.shiftKey ? 'first' : 'last']().focus();
        }
      }).on('click', e => {
        e.preventDefault();

        if (typeof clickHandler === 'function') {
          callCallback({
            func: clickHandler,
            params: [e],
          });
        }
      });
      this.$dialogButtons.append($button);
    });
    if (!append) {
      if (this.modalInstance) this.modalInstance.dispose();
      const overlayEl = this.$dialogOverlay.get(0);
      if (!overlayEl) throw new Error('Missing overlayEl');
      this.modalInstance = new ModalClass(overlayEl, {
        backdrop: 'static',
        keyboard: false,
      });
      this.modalInstance.show();
      this.$dialogOverlay.one('shown.bs.modal', () => {
        this.setFocus();
        Time.update();

        callCallback({
          func: callback,
          params: [$requestContentDiv as never],
        });
        if (append) {
          let $lastdiv = this.$dialogContent.children().last();
          if (appendingToRequest) {
            const $notice = $lastdiv.children('.notice').last();
            if ($notice.length) $lastdiv = $notice;
          }
          this.$dialogOverlay.stop().animate(
            {
              scrollTop: `+=${
                $lastdiv.position().top + parseFloat($lastdiv.css('margin-top')) + parseFloat($lastdiv.css('border-top-width'))}`,
            },
            'fast',
          );
        }
      });
    }
  }

  fail(title?: DialogParams['title'], content?: DialogParams['content']) {
    this.display({
      type: 'fail',
      title,
      content,
      buttons: { ...this.closeButton },
    });
  }

  success(title?: DialogParams['title'], content?: DialogParams['content'], buttons?: true | JQuery[], callback?: DialogCallback) {
    let btnObj: ButtonsRecord = {};
    if (buttons === true) btnObj = this.closeButton;
    else if (Array.isArray(buttons)) {
      $.each(buttons, (_, $elUpper) => {
        (function ($el) {
          btnObj[$el.html() || ''] = function () {
            $el.trigger('click');
          };
        })($elUpper);
      });
    }
    this.display({
      type: 'success',
      title,
      content,
      buttons: btnObj,
      callback,
    });
  }

  wait(title: string | false, additionalInfo?: string) {
    const localAdditionalInfo = typeof additionalInfo !== 'string' ? defaultContent.wait : additionalInfo;
    this.display({
      type: 'wait',
      title,
      content: `${capitalize(localAdditionalInfo)}&hellip;`,
    });
  }

  request({
    title,
    content,
    confirmBtn,
    callback,
  }: DialogRequestOptions): void {
    const buttons: ButtonsRecord = {};
    let formId: string | undefined;
    if (typeof content !== 'string') formId = content.attr('id');
    else {
      const match = content.match(/<form\sid=["']([^"']+)["']/);
      if (match) [, formId] = match;
    }
    if (confirmBtn !== false) {
      if (formId) buttons[confirmBtn || window.Laravel.dialog.submit] = {
        submit: true,
        form: formId,
      };
      buttons[window.Laravel.dialog.cancel] = () => {
        this.close();
      };
    } else buttons[window.Laravel.dialog.close] = {
      action: () => this.close(),
      form: formId,
    };

    this.display({
      type: 'request',
      title,
      content,
      buttons,
      callback,
    });
  }

  confirm({
    title,
    content,
    btnTextArray,
    handlerFunc,
  }: DialogConfirmOptions): void {
    const localHandlerFunc = handlerFunc || ((b: boolean) => {
      if (b) this.close();
    });

    const localBtnTextArray = Array.isArray(btnTextArray)
      ? btnTextArray
      : [window.Laravel.dialog.yes, window.Laravel.dialog.no];

    const buttons: ButtonsRecord = {
      [localBtnTextArray[0]]: () => {
        callCallback({
          func: localHandlerFunc,
          params: [true],
        });
      },
      [localBtnTextArray[1]]: () => {
        callCallback({
          func: localHandlerFunc,
          params: [false],
        });
        this.close();
      },
    };
    this.display({
      type: 'confirm',
      title,
      content,
      buttons,
    });
  }

  info(title: string, content: string | JQuery, callback?: VoidFunction) {
    this.display({
      type: 'info',
      title,
      content,
      buttons: this.closeButton,
      callback,
    });
  }

  setFocusedElement($el: JQuery | unknown) {
    if ($el instanceof jQuery) this.$focusedElement = $el as unknown as JQuery;
  }

  private storeFocus() {
    if (typeof this.$focusedElement !== 'undefined') return;
    const $focus = $(':focus');
    this.$focusedElement = $focus.length > 0 ? $focus.last() : undefined;
  }

  private restoreFocus() {
    if (typeof this.$focusedElement !== 'undefined') {
      this.$focusedElement.focus();
      this.$focusedElement = undefined;
    }
  }

  private setFocus() {
    const $inputs = this.$dialogContent.find('input,select,textarea').filter(':visible');
    const $actions = this.$dialogButtons.children();
    if ($inputs.length > 0) $inputs.first().focus();
    else if ($actions.length > 0 && this.open?.type !== 'info') $actions.first().focus();
  }

  private controlInputs(disable: boolean) {
    const $inputs = this.$dialogContent
      .children(':not(#dialogButtons)')
      .last()
      .add(this.$dialogButtons)
      .find('input, button, select, textarea');

    if (disable) $inputs.filter(':not(:disabled)').addClass('temp-disable');
    else $inputs.filter('.temp-disable').removeClass('temp-disable');
    setElDisabled($inputs.filter(':not(:disabled)'), disable);
  }

  close(callback?: (closed: boolean) => void): void {
    if (!this.isOpen() || !this.modalInstance) {
      callCallback({
        func: callback,
        params: [false],
      });
      return;
    }

    this.modalInstance.hide();
    this.$dialogOverlay.one('hidden.bs.modal', () => {
      this.$dialogOverlay.remove();
      this.open = undefined;
      this.restoreFocus();
      callCallback({
        func: callback,
        params: [true],
      });
    });
  }

  clearNotice(regexp?: RegExp): boolean {
    const $notice = this.$dialogContent.children(':not(#dialogButtons)').children('.notice:last-child');
    if (!$notice.length) return false;

    if (typeof regexp === 'undefined' || regexp.test($notice.html())) {
      $notice.hide();
      if ($notice.hasClass('info')) this.controlInputs(false);
      return true;
    }
    return false;
  }
}

export const Dialog = new DialogManager();
