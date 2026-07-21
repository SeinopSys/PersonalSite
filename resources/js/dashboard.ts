function esc(s: string): string {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

interface GraphNode { id: string; type: 'connection' | 'source'; color: string | null; icon: string | null; }
interface GraphEdge { from: string; to: string; kind: 'introduced' | 'mutual'; }
interface GraphResponse { seed: number; nodes: GraphNode[]; edges: GraphEdge[]; }

// Deterministic PRNG (mulberry32) so the same seed always produces the same sequence - used instead of
// Math.random() throughout the layout simulation so a given user's graph looks the same on every reload.
/* eslint-disable no-bitwise -- bit-twiddling is inherent to this well-known PRNG */
function mulberry32(seed: number): () => number {
  let state = seed;
  return () => {
    state |= 0;
    state = (state + 0x6d2b79f5) | 0;
    let t = Math.imul(state ^ (state >>> 15), 1 | state);
    t ^= (t + Math.imul(t ^ (t >>> 7), 61 | t));
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
/* eslint-enable no-bitwise */

// Fallback for connections with no source (or a source category with no assigned color) - matches
// ConnMan's own "person" node color (src/components/App.vue groups.person.color). Connections whose
// source's category has a color set (see the Connections page's "Category colors" section) are marked
// with that color instead.
const NODE_COLOR = '#6181b8';

function renderConnectionsGraph(canvas: HTMLCanvasElement, data: GraphResponse): void {
  const dpr = window.devicePixelRatio || 1;
  const width = canvas.clientWidth || 400;
  const height = canvas.clientHeight || 260;
  const el = canvas;
  el.width = width * dpr;
  el.height = height * dpr;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  ctx.scale(dpr, dpr);

  const rng = mulberry32(data.seed || 1);

  interface SimNode extends GraphNode { x: number; y: number; vx: number; vy: number; }
  // Layout runs in an unbounded virtual space - clamping positions to the canvas each step is what
  // made disconnected nodes pile up flush against the edges, producing a square/grid look. Instead,
  // let the simulation settle naturally, then fit the resulting bounding box into the canvas below.
  const nodes: SimNode[] = data.nodes.map(n => ({
    ...n,
    x: (rng() - 0.5) * 200,
    y: (rng() - 0.5) * 200,
    vx: 0,
    vy: 0,
  }));
  const byId = new Map(nodes.map(n => [n.id, n]));
  const edges = data.edges.filter(e => byId.has(e.from) && byId.has(e.to));

  // Minimal force-directed layout: node repulsion, spring edges, weak center gravity (only to stop
  // isolated nodes drifting off to infinity, not to enforce even canvas coverage).
  const iterations = nodes.length > 0 ? 300 : 0;
  for (let iter = 0; iter < iterations; iter++) {
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a = nodes[i]; const b = nodes[j];
        let dx = a.x - b.x; let dy = a.y - b.y;
        let distSq = dx * dx + dy * dy;
        if (distSq < 1) { dx = rng() - 0.5; dy = rng() - 0.5; distSq = 1; }
        const force = 1200 / distSq;
        const dist = Math.sqrt(distSq);
        const fx = (dx / dist) * force; const fy = (dy / dist) * force;
        a.vx += fx; a.vy += fy;
        b.vx -= fx; b.vy -= fy;
      }
    }
    edges.forEach(e => {
      const a = byId.get(e.from)!; const b = byId.get(e.to)!;
      const dx = b.x - a.x; const dy = b.y - a.y;
      const dist = Math.sqrt(dx * dx + dy * dy) || 1;
      const targetDist = 55;
      const force = (dist - targetDist) * 0.03;
      const fx = (dx / dist) * force; const fy = (dy / dist) * force;
      a.vx += fx; a.vy += fy;
      b.vx -= fx; b.vy -= fy;
    });
    nodes.forEach(n => {
      const node = n;
      node.vx += -node.x * 0.0005;
      node.vy += -node.y * 0.0005;
      node.vx *= 0.86; node.vy *= 0.86;
      // Clamp per-step speed so a single close-encounter repulsion spike can't fling one node far
      // away from the rest - that single outlier would otherwise dominate the bounding box below and
      // force every other (properly spread-out) node to be fit-scaled down into a tiny central clump.
      const speed = Math.sqrt(node.vx * node.vx + node.vy * node.vy);
      const maxSpeed = 25;
      if (speed > maxSpeed) { node.vx = (node.vx / speed) * maxSpeed; node.vy = (node.vy / speed) * maxSpeed; }
      node.x += node.vx; node.y += node.vy;
    });
  }

  // Fit the settled layout's bounding box into the canvas with padding, preserving aspect ratio.
  const padding = 20;
  const xs = nodes.map(n => n.x); const ys = nodes.map(n => n.y);
  const minX = Math.min(...xs, 0); const maxX = Math.max(...xs, 0);
  const minY = Math.min(...ys, 0); const maxY = Math.max(...ys, 0);
  const spanX = Math.max(maxX - minX, 1); const spanY = Math.max(maxY - minY, 1);
  const scale = Math.min((width - padding * 2) / spanX, (height - padding * 2) / spanY, 4);
  const midX = (minX + maxX) / 2; const midY = (minY + maxY) / 2;
  nodes.forEach(n => {
    const node = n;
    node.x = width / 2 + (node.x - midX) * scale;
    node.y = height / 2 + (node.y - midY) * scale;
  });

  // Node radius/stroke shrink along with the fit scale (floored for legibility) so a dense/large
  // network that had to be scaled down a lot doesn't end up with fixed-size circles overlapping each
  // other and hiding the edges/arrows between them.
  const nodeRadius = Math.max(2.5, Math.min(6, 6 * scale));
  const edgeLineWidth = Math.max(0.75, Math.min(1.5, 1.5 * scale));

  // A source ("met via" this) is drawn as a single hub node - visibly larger than the plain connection
  // dots, and growing further with how many connections point at it, rather than as a small dot
  // repeated once per connection.
  const sourceBaseRadius = Math.max(6, Math.min(14, 14 * scale));
  const sourceMaxRadius = sourceBaseRadius * 2.2;
  const inDegreeById = new Map<string, number>();
  edges.forEach(e => inDegreeById.set(e.to, (inDegreeById.get(e.to) ?? 0) + 1));
  const nodeRadiusFor = (n: SimNode): number => {
    if (n.type !== 'source') return nodeRadius;
    const degree = inDegreeById.get(n.id) ?? 0;
    return Math.min(sourceMaxRadius, sourceBaseRadius * (1 + Math.log2(degree + 1) * 0.35));
  };

  const iconImages = new Map<string, HTMLImageElement>();

  const draw = () => {
    ctx.clearRect(0, 0, width, height);

    // Pass 1: edge lines, under everything.
    edges.forEach(e => {
      const a = byId.get(e.from)!; const b = byId.get(e.to)!;
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.strokeStyle = 'rgba(137,135,129,0.5)';
      ctx.lineWidth = edgeLineWidth;
      ctx.stroke();
    });

    // Pass 2: node circles, on top of lines.
    nodes.forEach(n => {
      const r = nodeRadiusFor(n);
      const icon = n.icon ? iconImages.get(n.icon) : undefined;
      if (icon && icon.complete && icon.naturalWidth > 0) {
        ctx.save();
        ctx.beginPath();
        ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
        ctx.clip();
        // Cover-fit the (already-square) icon into the circle's bounding box.
        ctx.drawImage(icon, n.x - r, n.y - r, r * 2, r * 2);
        ctx.restore();

        ctx.beginPath();
        ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
        ctx.lineWidth = Math.max(1.5, edgeLineWidth);
        ctx.strokeStyle = n.color ?? NODE_COLOR;
        ctx.stroke();
        return;
      }

      ctx.beginPath();
      ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
      ctx.fillStyle = n.color ?? NODE_COLOR;
      ctx.fill();
      ctx.lineWidth = Math.max(0.75, edgeLineWidth);
      ctx.strokeStyle = '#fcfcfb';
      ctx.stroke();
    });

    // Pass 3: "introduced by" arrowheads, on top of everything so they're never hidden under a node.
    const arrowLen = Math.max(3, Math.min(7, 7 * scale));
    edges.forEach(e => {
      if (e.kind !== 'introduced') return;
      const a = byId.get(e.from)!; const b = byId.get(e.to)!;
      const dx = b.x - a.x; const dy = b.y - a.y;
      const dist = Math.sqrt(dx * dx + dy * dy) || 1;
      const ux = dx / dist; const uy = dy / dist;
      const tipX = b.x - ux * (nodeRadiusFor(b) + arrowLen); const tipY = b.y - uy * (nodeRadiusFor(b) + arrowLen);
      const angle = Math.atan2(dy, dx);
      ctx.beginPath();
      ctx.moveTo(tipX, tipY);
      ctx.lineTo(tipX - arrowLen * Math.cos(angle - Math.PI / 6), tipY - arrowLen * Math.sin(angle - Math.PI / 6));
      ctx.lineTo(tipX - arrowLen * Math.cos(angle + Math.PI / 6), tipY - arrowLen * Math.sin(angle + Math.PI / 6));
      ctx.closePath();
      ctx.fillStyle = 'rgba(82,81,78,0.9)';
      ctx.fill();
    });
  };

  draw();

  // Icons load asynchronously - kick off loading for every distinct URL up front and redraw once
  // each one lands, so the graph doesn't have to wait for all of them before showing anything.
  const iconUrls = new Set(nodes.map(n => n.icon).filter((url): url is string => !!url));
  iconUrls.forEach(url => {
    const img = new Image();
    img.onload = () => draw();
    img.src = url;
    iconImages.set(url, img);
  });
}

const graphCanvas = document.getElementById('connections-graph') as HTMLCanvasElement | null;
if (graphCanvas) {
  fetch('/connections/graph')
    .then(r => r.json() as Promise<GraphResponse>)
    .then(data => renderConnectionsGraph(graphCanvas, data))
    .catch(() => {
      graphCanvas.replaceWith(document.createTextNode('Failed to load connections graph.'));
    });
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
interface Highlight { label: string; minutes: number; events?: HighlightEvent[]; words?: string[]; }

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

function boldWords(text: string, words: string[]): string {
  return words.reduce((html, word) => {
    const escapedWord = esc(word);
    return html.split(escapedWord).join(`<strong>${escapedWord}</strong>`);
  }, esc(text));
}

function renderAvailRow(row: AvailRow, index: number): string {
  const labelSpan = row.notAvail
    ? '<span class="text-muted">Not available</span>'
    : (() => {
      const parts = [
        row.sleepPct > 0 ? `<span>${esc(index > 0 ? `${row.sleepPct}%` : row.sleepLabel)} sleep</span>` : '',
        row.workPct > 0 ? `<span class="text-primary">${esc(index > 0 ? `${row.workPct}%` : row.workLabel!)} work</span>` : '',
        row.busyPct > 0 ? `<span class="text-danger">${esc(index > 0 ? `${row.busyPct}%` : row.busyLabel!)} busy</span>` : '',
        row.freePct !== null ? `<span class="text-secondary">${esc(index > 0 ? `${row.freePct}%` : row.freeLabel!)} free</span>` : '',
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
        const eventsMap = new Map<string, HighlightEvent[]>();
        const wordsMap = new Map<string, string[]>();
        const allHighlights = [...data.highlights, ...rest];
        allHighlights.forEach((f, i) => {
          eventsMap.set(String(i), f.events ?? []);
          wordsMap.set(String(i), f.words ?? []);
        });

        const btnHtml = (f: Highlight, i: number, cls: string) => `<button class="btn btn-link p-0 text-start text-decoration-none highlight-events-btn ${cls}"
                   data-idx="${i}" data-label="${esc(f.label)}">${esc(f.label)}</button>`;

        const restRow = rest.length > 0
          ? `<li class="d-flex justify-content-between text-muted">
               <span>${rest.map((f, i) => btnHtml(f, topCount + i, 'text-muted')).join(', ')}</span>
               <span class="ms-2 flex-shrink-0">&le;&nbsp;${esc(fmtMin(rest[0].minutes))}</span>
             </li>`
          : '';

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
              const idx = btn.dataset.idx ?? '';
              const events = eventsMap.get(idx) ?? [];
              const words = wordsMap.get(idx) ?? [];
              modalLabel.textContent = btn.dataset.label ?? '';
              modalBody.innerHTML = events.length > 0
                ? events.map(e => `<tr><td>${boldWords(e.name, words)}</td><td class="text-nowrap">${esc(e.start)}</td><td class="text-nowrap">${esc(e.end)}</td></tr>`).join('')
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
    });
}
