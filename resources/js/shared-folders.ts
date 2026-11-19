// Small conveniences for the shared folder pages; everything else works as plain forms.
document.querySelectorAll<HTMLInputElement>('input[data-share-url]').forEach(input => {
  input.addEventListener('focus', () => input.select());
});

document.querySelectorAll<HTMLButtonElement>('button[data-copy-target]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.querySelector<HTMLInputElement>(button.dataset.copyTarget!);
    if (!input) return;
    input.select();
    navigator.clipboard?.writeText(input.value).catch(() => document.execCommand('copy'));
  });
});

// Forms that revoke or delete something ask first
document.querySelectorAll<HTMLFormElement>('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', e => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(form.dataset.confirm)) e.preventDefault();
  });
});
