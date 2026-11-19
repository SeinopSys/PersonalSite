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

// Uploads go out one file per request, in order, so no single request is bigger than the largest allowed file.
// Without JavaScript the form still works as one plain multi-file request.
const sharedForm = document.querySelector<HTMLFormElement>('#shared-upload-form');
const sharedInput = document.querySelector<HTMLInputElement>('#shared-file-input');
const sharedZone = document.querySelector<HTMLElement>('#shared-dropzone');
const sharedQueue = document.querySelector<HTMLElement>('#shared-queue');

function sendFile(form: HTMLFormElement, file: File, onProgress: (percent: number) => void): Promise<void> {
  return new Promise((resolve, reject) => {
    const body = new FormData();
    body.append('_token', (form.querySelector<HTMLInputElement>('input[name=_token]'))!.value);
    body.append('files[]', file);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
    });
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve();
        return;
      }
      let message = `HTTP ${xhr.status}`;
      try {
        const json = JSON.parse(xhr.responseText);
        message = (json.errors && Object.values<string[]>(json.errors)[0]?.[0]) || json.message || message;
      } catch { /* not JSON, keep the status */ }
      reject(new Error(message));
    });
    xhr.addEventListener('error', () => reject(new Error('Network error')));
    xhr.send(body);
  });
}

let sharedBusy = false;
async function uploadQueue(form: HTMLFormElement, files: File[]) {
  if (files.length === 0 || sharedBusy || !sharedQueue) return;
  sharedBusy = true;
  const maxBytes = Number(form.dataset.maxBytes) || Infinity;
  const rows = files.map(file => {
    const row = document.createElement('li');
    row.className = 'text-break';
    sharedQueue.appendChild(row);
    const set = (text: string, cls = 'text-body-secondary') => {
      row.textContent = `${file.name}: ${text}`;
      row.className = `text-break ${cls}`;
    };
    set(form.dataset.msgWaiting || '');
    return set;
  });

  let failed = 0;
  for (let i = 0; i < files.length; i += 1) {
    if (files[i].size > maxBytes) {
      rows[i](form.dataset.msgTooLarge || '', 'text-danger');
      failed += 1;
      continue; // eslint-disable-line no-continue
    }
    rows[i]('0%');
    try {
      // eslint-disable-next-line no-await-in-loop
      await sendFile(form, files[i], percent => rows[i](`${percent}%`));
      rows[i](form.dataset.msgDone || '', 'text-success');
    } catch (e) {
      rows[i](`${form.dataset.msgFailed}: ${(e as Error).message}`, 'text-danger');
      failed += 1;
    }
  }
  sharedBusy = false;
  // The file list is rendered by the server, so show it again once everything went through; failures stay readable
  if (failed === 0) window.location.reload();
}

if (sharedForm && sharedInput && sharedZone) {
  sharedForm.addEventListener('submit', e => {
    e.preventDefault();
    const files = Array.from(sharedInput.files || []);
    sharedInput.value = '';
    uploadQueue(sharedForm, files);
  });

  // Files dropped anywhere on the page join the queue; the zone only highlights to show where they go
  const isFileDrag = (e: DragEvent) => Array.from(e.dataTransfer?.types || []).includes('Files');
  let depth = 0;
  window.addEventListener('dragenter', e => {
    if (!isFileDrag(e)) return;
    depth += 1;
    sharedZone.classList.add('border-primary');
  });
  window.addEventListener('dragleave', e => {
    if (!isFileDrag(e)) return;
    depth = Math.max(depth - 1, 0);
    if (depth === 0) sharedZone.classList.remove('border-primary');
  });
  window.addEventListener('dragover', e => {
    if (isFileDrag(e)) e.preventDefault();
  });
  window.addEventListener('drop', e => {
    if (!isFileDrag(e)) return;
    e.preventDefault();
    depth = 0;
    sharedZone.classList.remove('border-primary');
    uploadQueue(sharedForm, Array.from(e.dataTransfer?.files || []));
  });
}
