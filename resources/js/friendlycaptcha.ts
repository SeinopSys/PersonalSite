import { setElDisabled } from './utils';
import { FriendlyCaptchaSDK  } from '@friendlycaptcha/sdk';

$(() => {
  const { captchaKey } = window.Laravel;

  if (typeof captchaKey !== 'string') return;

  if (captchaKey === '') {
    console.error('You haven\'t set your Site Key for Friendly Captcha v3. Get it on https://developer.friendlycaptcha.com/docs/');
    return;
  }
  const sdk = new FriendlyCaptchaSDK();

  const getSubmitEls = ($form: JQuery<HTMLElement>) => (
    $form.find<HTMLElement>('button:not([type]), input[type="submit"]')
  );

  $<HTMLElement>('.frc-captcha')
    .each((_, containerEl) => {
      if (!containerEl) throw new Error('Missing containerEl');
      const widget = sdk.createWidget({
        sitekey: captchaKey,
        element: containerEl,
      });
      const $container = $<HTMLElement>(containerEl);
      const $form = $container.closest('form');
      const disableButtons = () => {
        setElDisabled(getSubmitEls($form), true);
      };
      disableButtons();

      widget.addEventListener('frc:widget.complete', () => {
        setElDisabled(getSubmitEls($form), false);
      });
      widget.addEventListener('frc:widget.error', disableButtons);
      widget.addEventListener('frc:widget.expire', disableButtons);
    });
});

