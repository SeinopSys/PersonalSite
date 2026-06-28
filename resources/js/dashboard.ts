const modalEl = document.getElementById('uploadPreviewModal');
if (modalEl) {
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
