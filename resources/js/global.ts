import { Dialog } from './dialog';
import { Key } from './utils/Key';

declare global {
  interface Window {
    Laravel: {
      locale: 'hu' | 'en';
      csrfToken: string;
      jsLocales: Record<string, string>;
      dialog: Record<string, string>;
      ajaxErrors: Record<number, string>;
      git: {
        commit_id: string;
      };
      captchaKey?: string;
    };
    bootstrap: { Modal: import('bootstrap').Modal };
  }
}

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
