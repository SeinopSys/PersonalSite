function esc(s: string): string {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function fmtMin(m: number): string {
  if (m >= 1440) {
    const d = Math.floor(m / 1440);
    const r = m % 1440;
    return `${d}d ${Math.floor(r / 60)}:${String(r % 60).padStart(2, '0')}`;
  }
  return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`;
}

interface AvailRow {
  title: string;
  notAvail: boolean;
  sleepLabel: string; sleepPct: number; sleepBarPct: number;
  workLabel: string | null; workPct: number; workBarPct: number;
  busyLabel: string | null; busyPct: number; busyBarPct: number;
  freeLabel: string | null; freePct: number | null;
}

interface HighlightEvent { name: string; start: string; end: string; }
interface Highlight { label: string; minutes: number; events?: HighlightEvent[]; }

interface AvailResponse {
  error?: string;
  rows?: AvailRow[];
  highlights?: Highlight[];
  highlightsRest?: Highlight[];
  highlightsNoTime?: Highlight[];
}

interface Upload { preview: string; full: string; name: string; }

interface UploadResponse {
  error?: string;
  usedSpace?: string;
  quotaSpace?: string;
  usedPct?: number;
  uploads?: Upload[];
}

function renderAvailRow(row: AvailRow): string {
  const labelSpan = row.notAvail
    ? '<span class="text-muted">Not available</span>'
    : (() => {
      const parts = [
        row.sleepPct > 0 ? `<span>${esc(row.sleepLabel)} sleep</span>` : '',
        row.workPct > 0 ? `<span class="text-primary">${esc(row.workLabel!)} work</span>` : '',
        row.busyPct > 0 ? `<span class="text-danger">${esc(row.busyLabel!)} busy</span>` : '',
        row.freePct !== null ? `<span class="text-secondary">${esc(row.freeLabel!)} free</span>` : '',
      ].filter(Boolean);
      return `<span>${parts.join(' &middot; ')}</span>`;
    })();

  const bars = [
    row.sleepBarPct > 0 ? `<div class="progress-bar bg-secondary" style="width:${row.sleepBarPct}%" title="sleep"></div>` : '',
    row.workBarPct > 0 ? `<div class="progress-bar bg-primary" style="width:${row.workBarPct}%" title="${row.workPct}% work"></div>` : '',
    row.busyBarPct > 0 ? `<div class="progress-bar bg-danger" style="width:${row.busyBarPct}%" title="${row.busyPct}% busy"></div>` : '',
  ].join('');

  return `
    <div class="mb-3">
      <div class="d-flex justify-content-between small mb-1">
        <span class="fw-semibold">${esc(row.title)}</span>
        ${labelSpan}
      </div>
      <div class="progress" style="height:8px">${bars}</div>
    </div>`;
}

function attachUploadPreviews(): void {
  const modalEl = document.getElementById('uploadPreviewModal');
  if (!modalEl) return;
  const modal = new window.bootstrap.Modal(modalEl);
  document.querySelectorAll<HTMLAnchorElement>('.dashboard-upload-preview').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      const { full, name } = el.dataset;
      (document.getElementById('uploadPreviewImg') as HTMLImageElement).src = full ?? '';
      (document.getElementById('uploadPreviewImg') as HTMLImageElement).alt = name ?? '';
      (document.getElementById('uploadPreviewOpen') as HTMLAnchorElement).href = full ?? '';
      (document.getElementById('uploadPreviewModalLabel') as HTMLElement).textContent = name ?? '';
      modal.show();
    });
  });
}

const availEl = document.getElementById('avail-stats');
if (availEl) {
  fetch('/dashboard/stats/availability')
    .then(r => r.json() as Promise<AvailResponse>)
    .catch(() => ({ error: 'fetch_failed' }) as AvailResponse)
    .then(data => {
      if (data.error || !data.rows) {
        availEl.innerHTML = '<p class="text-danger mb-0"><span class="fa fa-exclamation-circle me-1"></span>Failed to fetch calendar data.</p>';
        return;
      }
      availEl.innerHTML = data.rows.map(renderAvailRow).join('');

      const highlightListEl = document.getElementById('highlight-list');
      if (highlightListEl && data.highlights) {
        const rest = data.highlightsRest ?? [];
        const topCount = data.highlights.length;
        const restRow = rest.length > 0
          ? `<li class="d-flex justify-content-between text-muted">
               <span>${rest.map((f, i) => btnHtml(f, topCount + i, 'text-muted')).join(', ')}</span>
               <span class="ms-2 flex-shrink-0">&le;&nbsp;${esc(fmtMin(rest[0].minutes))}</span>
             </li>`
          : '';
        const eventsMap = new Map<string, HighlightEvent[]>();
        const allHighlights = [...data.highlights, ...rest];
        allHighlights.forEach((f, i) => eventsMap.set(String(i), f.events ?? []));

        const btnHtml = (f: Highlight, i: number, cls: string) =>
          `<button class="btn btn-link p-0 text-start text-decoration-none highlight-events-btn ${cls}"
                   data-idx="${i}" data-label="${esc(f.label)}">${esc(f.label)}</button>`;

        highlightListEl.innerHTML = data.highlights.map((f, i) => `
          <li class="d-flex justify-content-between">
            ${btnHtml(f, i, 'text-body')}
            <span class="ms-2 flex-shrink-0">${esc(fmtMin(f.minutes))}</span>
          </li>`).join('') + restRow;

        const modalEl = document.getElementById('highlightEventsModal');
        if (modalEl) {
          const modal = new window.bootstrap.Modal(modalEl);
          const modalLabel = document.getElementById('highlightEventsModalLabel')!;
          const modalBody = document.getElementById('highlightEventsModalBody')!;
          highlightListEl.querySelectorAll<HTMLButtonElement>('.highlight-events-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              const events = eventsMap.get(btn.dataset.idx ?? '') ?? [];
              modalLabel.textContent = btn.dataset.label ?? '';
              modalBody.innerHTML = events.length > 0
                ? events.map(e => `<tr><td>${esc(e.name)}</td><td class="text-nowrap">${esc(e.start)}</td><td class="text-nowrap">${esc(e.end)}</td></tr>`).join('')
                : '<tr><td colspan="3" class="text-muted p-3">No matched events.</td></tr>';
              modal.show();
            });
          });
        }
      }

      const noTimeSectionEl = document.getElementById('highlight-no-time-section');
      const noTimeListEl = document.getElementById('highlight-no-time-list');
      if (noTimeSectionEl && noTimeListEl && data.highlightsNoTime) {
        if (data.highlightsNoTime.length > 0) {
          noTimeListEl.textContent = data.highlightsNoTime.map(f => f.label).join(', ');
          noTimeSectionEl.classList.remove('d-none');
        }
      }
    });
}

const uploadEl = document.getElementById('upload-stats');
if (uploadEl) {
  fetch('/dashboard/stats/uploads')
    .then(r => r.json() as Promise<UploadResponse>)
    .catch(() => ({ error: 'fetch_failed' }) as UploadResponse)
    .then(data => {
      if (data.error || data.usedPct === undefined) {
        uploadEl.innerHTML = '<p class="text-danger mb-0">Failed to load upload stats.</p>';
        return;
      }
      const pct = data.usedPct;
      const barClass = pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-warning' : 'bg-primary';
      const uploads = data.uploads ?? [];
      const uploadsHtml = uploads.length > 0
        ? `<div class="small fw-semibold mb-2 mt-3">Recent uploads</div>
           <div class="d-flex justify-content-between gap-2">
             ${uploads.map(u => `
               <a href="#" class="dashboard-upload-preview"
                  data-full="${esc(u.full)}"
                  data-name="${esc(u.name)}"
                  title="${esc(u.name)}"
                  style="flex:1;min-width:0;aspect-ratio:1;display:block">
                 <img src="${esc(u.preview)}"
                      alt="${esc(u.name)}"
                      style="width:100%;height:100%;object-fit:cover;border-radius:4px">
               </a>`).join('')}
           </div>`
        : '';

      uploadEl.innerHTML = `
        <div class="d-flex justify-content-between small mb-1">
          <span class="fw-semibold">Space used</span>
          <span>${esc(data.usedSpace!)} / ${esc(data.quotaSpace!)}</span>
        </div>
        <div class="progress mb-1" style="height:10px" role="progressbar"
             aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
        </div>
        <div class="small text-muted">${pct}% of quota used</div>
        ${uploadsHtml}`;

      if (uploads.length > 0) {
        attachUploadPreviews();
      }
    })
    });
}
