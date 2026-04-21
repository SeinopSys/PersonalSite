interface TimeSlot {
  start: string;
  end: string;
}

interface AvailabilityResponse {
  timezone: string;
  range: { start: string; end: string };
  free: TimeSlot[];
  error?: string;
}

function localDate(str: string): Date {
  return new Date(+str.slice(0, 4), +str.slice(5, 7) - 1, +str.slice(8, 10));
}

function fmtDate(d: Date): string {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function toMins(iso: string): number {
  return +iso.slice(11, 13) * 60 + +iso.slice(14, 16);
}

function getDatesInRange(start: string, end: string): string[] {
  const result: string[] = [];
  const d = localDate(start);
  const endD = localDate(end);
  while (d <= endD) {
    result.push(fmtDate(d));
    d.setDate(d.getDate() + 1);
  }
  return result;
}

function getOffset(iso: string): string {
  const m = iso.match(/([+-]\d{2}:\d{2}|Z)$/);
  return m ? (m[0] === 'Z' ? '+00:00' : m[0]) : '+00:00';
}

function splitAtMidnight(slot: TimeSlot): TimeSlot[] {
  const sd = slot.start.slice(0, 10);
  const ed = slot.end.slice(0, 10);
  if (sd === ed) return [slot];

  const off = getOffset(slot.start);
  const parts: TimeSlot[] = [];
  const cur = localDate(sd);
  const endD = localDate(ed);

  parts.push({start: slot.start, end: sd + 'T24:00:00' + off});
  cur.setDate(cur.getDate() + 1);

  while (cur < endD) {
    const ds = fmtDate(cur);
    parts.push({start: ds + 'T00:00:00' + off, end: ds + 'T24:00:00' + off});
    cur.setDate(cur.getDate() + 1);
  }

  if (toMins(slot.end) > 0) {
    parts.push({start: ed + 'T00:00:00' + off, end: slot.end});
  }

  return parts;
}

function formatDayLabel(dateStr: string): string {
  const d = localDate(dateStr);
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return days[d.getDay()] + '<br>' + d.getDate() + '&nbsp;' + months[d.getMonth()];
}

function buildCalendar(calDiv: HTMLElement, data: AvailabilityResponse): void {
  const PX_PER_MIN = 0.8;
  const HEADER_H = 40;
  const TIME_W = 42;

  const days = getDatesInRange(data.range.start.slice(0, 10), data.range.end.slice(0, 10));

  const byDate: Record<string, TimeSlot[]> = {};
  days.forEach(d => {
    byDate[d] = [];
  });
  data.free.forEach(slot => {
    splitAtMidnight(slot).forEach(part => {
      const d = part.start.slice(0, 10);
      if (byDate[d]) byDate[d].push(part);
    });
  });

  let viewMin = 1440, viewMax = 0;
  data.free.forEach(slot => {
    splitAtMidnight(slot).forEach(part => {
      viewMin = Math.min(viewMin, toMins(part.start));
      viewMax = Math.max(viewMax, toMins(part.end));
    });
  });
  if (viewMin >= viewMax) {
    viewMin = 8 * 60;
    viewMax = 22 * 60;
  }
  viewMin = Math.floor(Math.max(0, viewMin - 30) / 60) * 60;
  viewMax = Math.ceil(Math.min(1440, viewMax + 30) / 60) * 60;

  const totalH = (viewMax - viewMin) * PX_PER_MIN;

  let html = '<div style="display:flex;overflow-x:auto;font-size:0.8rem;user-select:none">';

  // Time axis
  html += `<div style="flex-shrink:0;width:${TIME_W}px">`;
  html += `<div style="height:${HEADER_H}px"></div>`;
  html += `<div style="position:relative;height:${totalH}px">`;
  for (let m = viewMin; m < viewMax; m += 60) {
    const y = (m - viewMin) * PX_PER_MIN;
    const label = String(Math.floor(m / 60) % 24).padStart(2, '0') + ':00';
    html += `<div style="position:absolute;top:${y}px;right:6px;transform:translateY(-50%);color:#6c757d;white-space:nowrap">${label}</div>`;
  }
  html += '</div></div>';

  // Day columns
  days.forEach(day => {
    const slots = byDate[day];
    html += '<div style="flex:1;min-width:80px;border-left:1px solid #dee2e6">';
    html += `<div style="height:${HEADER_H}px;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:600;border-bottom:2px solid #dee2e6">${formatDayLabel(day)}</div>`;
    html += `<div style="position:relative;height:${totalH}px;background:#f8f9fa">`;

    for (let m = viewMin; m <= viewMax; m += 60) {
      const y = (m - viewMin) * PX_PER_MIN;
      html += `<div style="position:absolute;top:${y}px;left:0;right:0;border-top:1px solid #dee2e6"></div>`;
    }

    slots.forEach(slot => {
      const sm = Math.max(toMins(slot.start), viewMin);
      const em = Math.min(toMins(slot.end), viewMax);
      if (sm >= em) return;
      const top = (sm - viewMin) * PX_PER_MIN;
      const height = Math.max(2, (em - sm) * PX_PER_MIN);
      html += `<div style="position:absolute;left:2px;right:2px;top:${top}px;height:${height}px;background:rgba(25,135,84,0.25);border-radius:3px;border-left:3px solid #198754"></div>`;
    });

    html += '</div></div>';
  });

  html += '</div>';
  calDiv.innerHTML = html;
}

document.querySelectorAll<HTMLInputElement>('.day-available-check').forEach(checkbox => {
  checkbox.addEventListener('change', () => {
    const row = checkbox.closest('tr');
    if (!row) return;
    row.querySelectorAll<HTMLInputElement>('.day-time-input').forEach(input => {
      input.disabled = !checkbox.checked;
    });
  });
});

const startInput = document.getElementById('avail-start') as HTMLInputElement | null;
const endInput = document.getElementById('avail-end') as HTMLInputElement | null;
const btn = document.getElementById('avail-fetch') as HTMLButtonElement | null;
const calDiv = document.getElementById('avail-calendar') as HTMLElement | null;

if (startInput && endInput && btn && calDiv) {
  const username = calDiv.dataset.username ?? '';

  // Default to current week (Mon–Sun)
  const now = new Date();
  const dow = now.getDay();
  const monday = new Date(now);
  monday.setDate(now.getDate() - (dow === 0 ? 6 : dow - 1));
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  startInput.value = fmtDate(monday);
  endInput.value = fmtDate(sunday);

  btn.addEventListener('click', () => {
    const s = startInput.value;
    const e = endInput.value;
    if (!s) return;

    btn.disabled = true;
    calDiv.innerHTML = '<div class="p-3 text-muted">Loading…</div>';

    let url = `/api/availability/${encodeURIComponent(username)}?start=${encodeURIComponent(s)}`;
    if (e) url += `&end=${encodeURIComponent(e)}`;

    fetch(url)
      .then(r => r.json() as Promise<AvailabilityResponse>)
      .then(data => {
        if (data.error) {
          calDiv.innerHTML = `<div class="p-3 text-danger">${data.error}</div>`;
        } else {
          buildCalendar(calDiv, data);
        }
      })
      .catch((err: Error) => {
        calDiv.innerHTML = `<div class="p-3 text-danger">Error: ${err.message}</div>`;
      })
      .finally(() => {
        btn.disabled = false;
      });
  });
}
