// Convert .serializeArray() result to object
export const mkData = <El extends HTMLElement>($el: JQuery<El>, obj?: Record<string, unknown>): Record<string, string> => {
  const tempData = $el.serializeArray();
  const data: Record<string, string> = {};
  $.each(tempData, (i, el) => {
    data[el.name] = el.value;
  });
  if (typeof obj === 'object') $.extend(data, obj);
  return data;
};

interface CallCallbackOptions<Params, Returns> {
  func?: ((...p: Params[]) => Returns),
  params?: Params[],
  def?: Returns,
}

/**
 * Checks if a variable is a function and if yes, runs it
 * If no, returns default value (undefined or value of def)
 */
export function callCallback<Params, Returns>({
  func,
  params,
  def,
}: CallCallbackOptions<Params, Returns>): Returns | undefined {
  if (typeof func !== 'function') return def;

  return func.apply(window, params || []);
}

// Make the first letter of the first or all word(s) uppercase
export const capitalize = (str: string, all = false): string => {
  if (all) return str.replace(/((?:^|\s)[a-z])/g, match => match.toUpperCase());
  return str.length === 1 ? str.toUpperCase() : str[0].toUpperCase() + str.substring(1);
};

export const strRepeat = (str: string, times = 1): string => new Array(times + 1).join(str);
export const pad = (
  str: string | { toString(): string },
  char = '0',
  len: number | string = 2,
  fromRight = false,
): string => {
  let localStr = typeof str !== 'string' ? str.toString() : str;
  let localLen = len;

  if (typeof localLen === 'string') {
    localLen = parseInt(localLen, 10);
  }
  if (!Number.isFinite(localLen) && Number.isNaN(localLen)) localLen = 2;

  if (localLen <= localStr.length) return localStr;
  const padStr = new Array(localLen - localStr.length + 1).join(char);
  localStr = fromRight ? localStr + padStr : padStr + localStr;

  return localStr;
};

export const translatePlaceholders = (
  string: unknown,
  params?: Record<string, string | { toString(): string }>,
): string => {
  if (typeof string !== 'string') {
    console.log(params);
    throw new TypeError('String expected as first argument');
  }
  let localString = string;
  if (params) $.each(params, (k, v) => {
    localString = localString.replace(new RegExp(`:${k}`, 'g'), String(v));
  });
  return localString;
};

export const roundTo = (number: number, precision: number): number => {
  const pow = 10 ** precision;
  return Math.round(number * pow) / pow;
};
export const rangeLimit = function (
  input: number,
  overflow: boolean,
  ...args: [number] | [number, number]
): number {
  let min = 0;
  let max = 0;
  switch (args.length) {
  case 1:
    [max] = args;
    break;
  case 2:
    [min, max] = args;
    break;
  default:
    throw new Error('Invalid number of parameters for $.rangeLimit');
  }
  let localInput = input;
  if (overflow) {
    if (localInput > max) localInput = min;
    else if (localInput < min) localInput = max;
  }
  return Math.min(max, Math.max(min, localInput));
};

export const setElDisabled = ($el: JQuery, disabled: boolean): void => {
  if (disabled) {
    $el.attr('disabled', 'true');
    return;
  }
  $el.removeAttr('disabled');
};

let $notif: JQuery | undefined;
/**
 * Copy any text to clipboard
 * Must be called from within an event handler
 */
export const copy = (text: string, e: { clientY: number, clientX: number }): boolean => {
  if (!document.queryCommandSupported('copy')) {
    // eslint-disable-next-line no-alert
    prompt('Copy with Ctrl+C, close with Enter', text);
    return true;
  }

  const $helper = $(document.createElement('textarea'));
  let success = false;
  $helper
    .css({
      opacity: 0,
      width: 0,
      height: 0,
      position: 'fixed',
      left: '-10px',
      top: '50%',
      display: 'block',
    })
    .text(text)
    .appendTo('body')
    .focus();
  $helper.get(0)?.select();

  try {
    success = document.execCommand('copy');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
  } catch (err) { /* ignore */
  }

  setTimeout(() => {
    $helper.remove();
    if (typeof $notif === 'undefined' || e) {
      if (typeof $notif === 'undefined') $notif = $(document.createElement('span'))
        .attr({
          id: 'copy-notify',
          class: `alert alert-${success ? 'success' : 'danger'}`,
        })
        .html(`<span class="fa fa-copy"></span> <span class="fa fa-${success ? 'check' : 'times'}"></span>`)
        .appendTo($(document.body));
      if (e) {
        const w = $notif.outerWidth() || 0;
        const h = $notif.outerHeight() || 0;
        const top = e.clientY - (h / 2);
        $notif.stop()
          .css({
            top,
            left: (e.clientX - (w / 2)),
            bottom: 'initial',
            right: 'initial',
            opacity: 1,
          })
          .animate({
            top: top - 20,
            opacity: 0,
          }, 600, function () {
            $(this)
              .remove();
            $notif = undefined;
          });
        return;
      }
      $notif.fadeTo('fast', 1);
    } else $notif.stop()
      .css('opacity', 1);
    $notif.delay(success ? 300 : 1000)
      .fadeTo('fast', 0, function () {
        $(this)
          .remove();
        $notif = undefined;
      });
  }, 1);

  return true;
};
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const mkAjaxHandler = <Data = any>(f: (this: Data, data: Data) => void) => (data: Data): void => {
  if (typeof data !== 'object') {
    console.log(data);
    $(window).triggerHandler('ajaxerror');
    return;
  }

  if (typeof f === 'function') f.call(data, data);
};
