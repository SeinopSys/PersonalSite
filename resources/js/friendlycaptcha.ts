import { WidgetInstance } from 'friendly-challenge';
import { setElDisabled } from './utils';

$(() => {
  const { captchaKey } = window.Laravel;

  if (typeof captchaKey !== 'string') return;

  if (captchaKey === '') {
    console.error('You haven\'t set your Site Key for Friendly Captcha v3. Get it on https://developer.friendlycaptcha.com/docs/');
    return;
  }

  const getSubmitEls = ($form: JQuery<HTMLElement>) => (
    $form.find<HTMLElement>('button:not([type]), input[type="submit"]')
  );

  $<HTMLElement>('.frc-captcha')
    .each((_, containerEl) => {
      if (!containerEl) throw new Error('Missing containerEl');
      const $container = $<HTMLElement>(containerEl);
      const $form = $container.closest('form');
      const enableButtons = () => {
        setElDisabled(getSubmitEls($form), false);
      };
      const disableButtons = () => {
        setElDisabled(getSubmitEls($form), true);
      };
      disableButtons();
      const widget = new WidgetInstance(containerEl, {
        sitekey: captchaKey,
        doneCallback: enableButtons,
        errorCallback: disableButtons,
        language: document.documentElement.lang as never,
      });

      $form.on('submit', () => {
        setTimeout(() => {
          widget.reset();
        }, 500);
      });
    });
});
