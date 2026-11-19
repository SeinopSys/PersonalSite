// Viewer for the public share page: opens images and videos full size over the page. Works with mouse (click the
// arrows, click outside to close), keyboard (arrows, Home/End, Escape) and touch (swipe sideways to move on, down
// to close, pinch to zoom). Without it the links on the page open the files themselves, so nothing depends on it.

interface GalleryItem {
  kind: 'image' | 'video';
  src: string;
  download: string;
  name: string;
  trigger: HTMLAnchorElement;
}

const ICON_PREV = '<path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/>';
const ICON_NEXT = '<path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>';
const ICON_CLOSE = '<path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>';
const ICON_DOWNLOAD = '<path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/>';

const SWIPE_DISTANCE = 50; // px sideways to count as a swipe
const CLOSE_DISTANCE = 90; // px downwards to close
const VIDEO_CONTROLS_HEIGHT = 64; // px at the bottom of a video that belong to its controls, not to swiping

function init(): void {
  const root = document.getElementById('gallery');
  if (!root) return;

  const items: GalleryItem[] = Array.from(root.querySelectorAll<HTMLAnchorElement>('a.gallery-item')).map(a => ({
    kind: a.dataset.kind === 'video' ? 'video' : 'image',
    src: a.dataset.src || a.href,
    download: a.dataset.download || a.href,
    name: a.dataset.name || '',
    trigger: a,
  }));
  if (items.length === 0) return;

  const label = (key: string) => root.dataset[`label${key}`] || '';

  function button(className: string, text: string, icon: string): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = `viewer-btn ${className}`;
    b.setAttribute('aria-label', text);
    b.title = text;
    b.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">${icon}</svg>`;
    return b;
  }

  const viewer = document.createElement('div');
  viewer.className = 'viewer';
  viewer.hidden = true;
  viewer.tabIndex = -1;
  viewer.setAttribute('role', 'dialog');
  viewer.setAttribute('aria-modal', 'true');

  const bar = document.createElement('div');
  bar.className = 'viewer-bar';
  const count = document.createElement('span');
  count.className = 'viewer-count';
  count.setAttribute('aria-live', 'polite');
  const nameEl = document.createElement('span');
  nameEl.className = 'viewer-name';
  const downloadLink = document.createElement('a');
  downloadLink.className = 'viewer-btn';
  downloadLink.setAttribute('download', '');
  downloadLink.setAttribute('aria-label', label('Download'));
  downloadLink.title = label('Download');
  downloadLink.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">${ICON_DOWNLOAD}</svg>`;
  const closeBtn = button('close', label('Close'), ICON_CLOSE);
  bar.append(count, nameEl, downloadLink, closeBtn);

  const stage = document.createElement('div');
  stage.className = 'viewer-stage';
  const prevBtn = button('viewer-nav prev', label('Prev'), ICON_PREV);
  const nextBtn = button('viewer-nav next', label('Next'), ICON_NEXT);
  const status = document.createElement('div');
  status.className = 'viewer-status';
  status.setAttribute('role', 'status');
  stage.append(status, prevBtn, nextBtn);

  viewer.append(bar, stage);
  document.body.appendChild(viewer);

  let index = 0;
  let isOpen = false;
  let pushedState = false;
  let opener: HTMLElement | null = null;
  let loadId = 0;
  let media: HTMLImageElement | HTMLVideoElement | null = null;

  function clearMedia(): void {
    if (!media) return;
    if (media instanceof HTMLVideoElement) {
      // Stops the download as well as the playback
      media.pause();
      media.removeAttribute('src');
      media.load();
    }
    media.remove();
    media = null;
  }

  function setStatus(kind: 'loading' | 'error' | 'none'): void {
    status.textContent = '';
    if (kind === 'loading') {
      const spinner = document.createElement('div');
      spinner.className = 'viewer-spinner';
      status.appendChild(spinner);
    } else if (kind === 'error') {
      status.textContent = label('Error');
    }
  }

  function preload(i: number): void {
    const item = items[i];
    if (item && item.kind === 'image') new Image().src = item.src;
  }

  function show(i: number): void {
    index = Math.min(Math.max(i, 0), items.length - 1);
    const item = items[index];
    loadId += 1;
    const mine = loadId;

    clearMedia();
    setStatus('loading');
    count.textContent = `${index + 1} / ${items.length}`;
    nameEl.textContent = item.name;
    viewer.setAttribute('aria-label', item.name);
    downloadLink.href = item.download;
    prevBtn.disabled = index === 0;
    nextBtn.disabled = index === items.length - 1;

    const ready = () => { if (mine === loadId) setStatus('none'); };
    const failed = () => { if (mine === loadId) setStatus('error'); };
    if (item.kind === 'video') {
      const video = document.createElement('video');
      video.controls = true;
      video.playsInline = true;
      video.autoplay = true; // opened by a click or tap, so playing with sound is allowed
      video.preload = 'metadata';
      video.addEventListener('loadeddata', ready);
      video.addEventListener('error', failed);
      video.src = item.src;
      media = video;
    } else {
      const img = document.createElement('img');
      img.alt = item.name;
      img.decoding = 'async';
      img.draggable = false;
      img.addEventListener('load', ready);
      img.addEventListener('error', failed);
      img.src = item.src;
      media = img;
    }
    stage.insertBefore(media, status);

    preload(index + 1);
    preload(index - 1);
  }

  function hide(): void {
    if (!isOpen) return;
    isOpen = false;
    loadId += 1;
    clearMedia();
    viewer.hidden = true;
    document.documentElement.classList.remove('viewer-open');
    if (opener && document.contains(opener)) opener.focus();
    opener = null;
  }

  // Set while the history entry added on open is being taken back out by close(); its popstate is not a user action
  let ignoreNextPop = false;

  function close(): void {
    const hadEntry = pushedState && window.history.state && window.history.state.galleryViewer;
    hide();
    // Closing by hand has to take the extra history entry (see open) back out, or "back" would do nothing visible
    if (hadEntry) {
      pushedState = false;
      ignoreNextPop = true;
      window.history.back();
    }
  }

  function open(i: number, from: HTMLElement): void {
    opener = from;
    isOpen = true;
    viewer.hidden = false;
    document.documentElement.classList.add('viewer-open');
    show(i);
    viewer.focus();
    // On a phone the back button/gesture closes the viewer instead of leaving the page
    try {
      window.history.pushState({ galleryViewer: true }, '');
      pushedState = true;
    } catch {
      pushedState = false;
    }
  }

  window.addEventListener('popstate', () => {
    if (ignoreNextPop) {
      ignoreNextPop = false;
      return;
    }
    // The back button or gesture
    pushedState = false;
    hide();
  });

  function move(delta: number): void {
    const next = index + delta;
    if (next >= 0 && next < items.length) show(next);
  }

  root.addEventListener('click', e => {
    const link = (e.target as Element).closest<HTMLAnchorElement>('a.gallery-item');
    // Let the browser handle "open in new tab" and the like
    if (!link || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    open(items.findIndex(item => item.trigger === link), link);
  });

  closeBtn.addEventListener('click', close);
  prevBtn.addEventListener('click', () => move(-1));
  nextBtn.addEventListener('click', () => move(1));
  // Clicking or tapping the empty space around the picture closes it
  stage.addEventListener('click', e => {
    if (e.target === stage || e.target === status) close();
  });

  document.addEventListener('keydown', e => {
    if (!isOpen) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === 'Tab') {
      // Keep focus inside the viewer
      const focusable = Array.from(viewer.querySelectorAll<HTMLElement>('button:not(:disabled), a[href], video'));
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;
      if (e.shiftKey && (active === first || active === viewer)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
      return;
    }
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    // With a video's own controls focused the arrow keys seek in it, as they should
    const onVideo = e.target instanceof HTMLVideoElement;
    if (e.key === 'ArrowLeft' && !onVideo) {
      e.preventDefault();
      move(-1);
    } else if (e.key === 'ArrowRight' && !onVideo) {
      e.preventDefault();
      move(1);
    } else if (e.key === 'Home' && !onVideo) {
      e.preventDefault();
      show(0);
    } else if (e.key === 'End' && !onVideo) {
      e.preventDefault();
      show(items.length - 1);
    }
  });

  // Touch and pen: swipe sideways to move on, down to close. Mouse input has the buttons.
  let start: { id: number; x: number; y: number } | null = null;
  stage.addEventListener('pointerdown', e => {
    if (e.pointerType === 'mouse' || !e.isPrimary) return;
    // A video's controls (seek bar...) sit at its bottom edge and have to keep their drags
    if (e.target instanceof HTMLVideoElement && e.clientY > e.target.getBoundingClientRect().bottom - VIDEO_CONTROLS_HEIGHT) return;
    start = { id: e.pointerId, x: e.clientX, y: e.clientY };
  });
  stage.addEventListener('pointerup', e => {
    if (!start || start.id !== e.pointerId) return;
    const dx = e.clientX - start.x;
    const dy = e.clientY - start.y;
    start = null;
    // While zoomed in, a drag pans the picture instead
    if (window.visualViewport && window.visualViewport.scale > 1.02) return;
    if (Math.abs(dx) > SWIPE_DISTANCE && Math.abs(dx) > Math.abs(dy) * 1.5) {
      move(dx < 0 ? 1 : -1);
    } else if (dy > CLOSE_DISTANCE && dy > Math.abs(dx) * 1.5) {
      close();
    }
  });
  stage.addEventListener('pointercancel', () => { start = null; });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
