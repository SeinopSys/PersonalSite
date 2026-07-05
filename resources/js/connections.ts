interface ConnectionEvent {
  name: string;
  start: string;
  end: string;
}

interface ConnectionEventsResponse {
  events?: ConnectionEvent[];
  error?: string;
}

// Scoped per-form (not a single fixed id) so the create form and every attribute definition's inline
// edit form can each independently show/hide their own type-specific option fields.
document.querySelectorAll<HTMLSelectElement>('.attribute-type-select').forEach(typeSelect => {
  const form = typeSelect.closest('form');
  if (!form) return;
  const optionGroups = form.querySelectorAll<HTMLElement>('.attribute-type-options');

  const sync = () => {
    const current = typeSelect.value;
    optionGroups.forEach(group => {
      const el = group;
      const forTypes = (el.dataset.for ?? '').split(',');
      const active = forTypes.includes(current);
      el.style.display = active ? '' : 'none';
      el.querySelectorAll<HTMLInputElement>('input').forEach(inputEl => {
        const field = inputEl;
        field.disabled = !active;
      });
    });
  };

  typeSelect.addEventListener('change', sync);
  sync();
});

// Both the sources list and the connections list mark their selected item with `.active` server-side -
// scroll it into view within its own scrollable list container, since the selected item can be well
// below the fold in a long alphabetical list.
document.querySelectorAll<HTMLElement>('.list-group-item.active').forEach(item => {
  item.scrollIntoView({ block: 'nearest' });
});

function copyTokenText(text: string): Promise<void> {
  if (navigator.clipboard) {
    return navigator.clipboard.writeText(text);
  }
  const el = document.createElement('textarea');
  el.value = text;
  el.style.cssText = 'position:fixed;opacity:0';
  document.body.appendChild(el);
  el.focus();
  el.select();
  try {
    document.execCommand('copy');
    return Promise.resolve();
  } catch {
    return Promise.reject(new Error('Copy failed'));
  } finally {
    document.body.removeChild(el);
  }
}

document.querySelectorAll<HTMLButtonElement>('.copy-token-btn').forEach(copyBtn => {
  const el = copyBtn;
  el.addEventListener('click', () => {
    const token = el.dataset.token ?? '';
    const orig = el.textContent;
    copyTokenText(token)
      .then(() => { el.textContent = 'Copied!'; })
      .catch(() => { el.textContent = 'Failed'; })
      .finally(() => { setTimeout(() => { el.textContent = orig; }, 1500); });
  });
});

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

document.querySelectorAll<HTMLElement>('.connection-events').forEach(containerEl => {
  const el = containerEl;
  const { connectionId } = el.dataset;
  if (!connectionId) return;

  fetch(`/connections/${encodeURIComponent(connectionId)}/events`)
    .then(r => r.json() as Promise<ConnectionEventsResponse>)
    .then(data => {
      if (data.error) {
        el.innerHTML = `<p class="text-danger small mb-0">${data.error}</p>`;
        return;
      }
      const events = data.events ?? [];
      if (events.length === 0) {
        el.innerHTML = '<p class="text-muted small mb-0">No matching calendar events found.</p>';
        return;
      }
      const items = events.map(e => `<li class="list-group-item small py-1">${escapeHtml(e.name)} <span class="text-muted">(${escapeHtml(e.start)} – ${escapeHtml(e.end)})</span></li>`).join('');
      el.innerHTML = `<ul class="list-group">${items}</ul>`;
    })
    .catch(() => {
      el.innerHTML = '<p class="text-danger small mb-0">Failed to load events.</p>';
    });
});
