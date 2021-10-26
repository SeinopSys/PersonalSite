import type { Modal } from 'bootstrap';
import { Dialog } from './dialog';
import { Key } from './utils/Key';
import { setElDisabled } from './utils';

declare global {
  interface Window {
    hcaptchaReady: VoidFunction;
    Laravel: {
      locale: 'hu' | 'en';
      csrfToken: string;
      jsLocales: Record<string, string>;
      dialog: Record<string, string>;
      ajaxErrors: Record<number, string>;
      git: {
        commit_id: string;
      };
      hcaptchaKey?: string;
    };
    hcaptcha: {
      render(el: HTMLElement, options: {
        sitekey: string,
        callback: (token: string) => void,
      }): string;
      execute(widgetId: string): void;
    };
    bootstrap: { Modal: Modal };
  }
}

window.hcaptchaReady = () => {
  const { hcaptchaKey } = window.Laravel;
  const { hcaptcha } = window;

  if (typeof hcaptchaKey === 'undefined') return;

  if (hcaptchaKey === '') {
    console.error('You haven\'t set your Site Key for hCaptcha v3. Get it on https://hcaptcha.com.');
    return;
  }

  const relevantInputName = 'h-captcha-response';

  const getKeyEl = ($form: JQuery<HTMLFormElement>) => {
    let $el = $form.find(`:input[name="${relevantInputName}"]`);
    if (!$el.length) {
      $el = $($(document.createElement('textarea')))
        .attr('name', relevantInputName)
        .appendTo($form);
    }
    return $el as JQuery<HTMLInputElement | HTMLTextAreaElement>;
  };

  const getSubmitEls = ($form: JQuery<HTMLFormElement>) => (
    $form.find<HTMLButtonElement | HTMLInputElement>('button:not([type]), input[type="submit"]')
  );

  $<HTMLFormElement>('form')
    .filter('[data-hcaptcha="true"]')
    .each((_, form) => {
      const $form = $<HTMLFormElement>(form);
      const $container = $form.find('.h-captcha');
      const containerEl = $container.get(0);
      if (!containerEl) throw new Error('Missing containerEl');
      const widgetId = hcaptcha.render(containerEl, {
        sitekey: hcaptchaKey,
        ...$container.data(),
        callback: (token: string) => {
          const $key = getKeyEl($form);
          $key.val(token);
          form.submit();
          // Reset the key for the next submission
          $key.val('');
          setElDisabled(getSubmitEls($form), false);
        },
      });
      $form.on('submit', e => {
        const $key = getKeyEl($form);
        const keyValue = $key.val();
        console.log(keyValue);
        if (typeof keyValue !== 'string' || keyValue.length === 0) {
          e.preventDefault();
          setElDisabled(getSubmitEls($form), true);
          hcaptcha.execute(widgetId);
          return;
        }
        // Reset the key for the next submission
        $key.val('');
      });
    });
};

// Create AJAX response handling function
$(window).on('ajaxerror', (...args) => {
  let details = '';
  if (args.length > 1) {
    const data = args.slice(1);
    if (data[1] === 'abort') return;
    details = ` Details:<pre><code>${data.slice(1).join('\n').replace(/</g, '&lt;')}</code></pre>Response body:`;
    const xdebug = /^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;
    if (xdebug.test(data[0].responseText)) details += `<div class="reset">${data[0].responseText.replace(xdebug, '$1')}</div>`;
    else if (typeof data[0].responseText === 'string') details += `<pre><code>${data[0].responseText.replace(/</g, '&lt;')}</code></pre>`;
  }
  Dialog.fail(false, `There was an error while processing your request.${details}`);
});

let lasturl: string | undefined;
const statusCodeHandlers: JQuery.Ajax.StatusCodeCallbacks<undefined> = {
  404() {
    const endpoint = (lasturl || '').replace(/</g, '&lt;').replace(/\//g, '/<wbr>');
    Dialog.fail(false, window.Laravel.ajaxErrors[404].replace(':endpoint', endpoint));
  },
  500() {
    Dialog.fail(false, window.Laravel.ajaxErrors[500]);
  },
  503() {
    Dialog.fail(false, window.Laravel.ajaxErrors[503]);
  },
};
$.ajaxSetup({
  dataType: 'json',
  error(...params) {
    const [xhr] = params;
    if (typeof statusCodeHandlers[xhr.status] !== 'function') $(window).triggerHandler('ajaxerror', params);
    $(document.body).removeClass('loading');
  },
  beforeSend(_, settings) {
    lasturl = settings.url;
  },
  statusCode: statusCodeHandlers,
  headers: { 'X-CSRF-TOKEN': window.Laravel.csrfToken },
});

$('#logout-link')
  .on('click', function (e) {
    e.preventDefault();

    Dialog.confirm({
      title: $(this).text().trim(),
      handlerFunc: sure => {
        if (!sure) return;

        Dialog.wait(false);

        $.post('/logout', () => {
          window.location.href = '/';
        });
      },
    });
  });

$(document.body).on('keydown', e => {
  if (!Dialog.isOpen() || e.keyCode !== Key.Tab) return;

  const $inputs = Dialog.$dialogContent.find(':input');
  const $focused = $inputs.filter(e.target);
  const idx = $inputs.index($focused);

  if ($focused.length === 0) {
    e.preventDefault();
    $inputs.first().focus();
  } else if (e.shiftKey) {
    if (idx === 0) {
      e.preventDefault();
      Dialog.$dialogButtons.find(':last').focus();
    } else {
      const $parent = $focused.parent();
      if (!$parent.is(Dialog.$dialogButtons)) return;
      if ($parent.children().first().is($focused)) {
        e.preventDefault();
        $inputs.eq($inputs.index($focused) - 1).focus();
      }
    }
  }
});
