function esc(s: string): string {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

interface Upload { preview: string; full: string; name: string; }

interface UploadResponse {
  error?: string;
  usedSpace?: string;
  quotaSpace?: string;
  usedPct?: number;
  uploads?: Upload[];
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
    });
}
