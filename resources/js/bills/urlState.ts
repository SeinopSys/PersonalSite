// Keeps the current view in the query string so a reload (or a copied link) shows the same tabs and pages.

export const readParam = (name: string): string | null => new URLSearchParams(window.location.search).get(name);

export const readPage = (name: string): number => {
  const value = Number(readParam(name));
  return Number.isInteger(value) && value >= 1 ? value : 1;
};

/** Sets or removes query parameters without adding history entries. A null value removes the parameter. */
export function writeParams(values: Record<string, string | number | null>): void {
  const params = new URLSearchParams(window.location.search);
  Object.entries(values).forEach(([key, value]) => {
    if (value === null) params.delete(key);
    else params.set(key, String(value));
  });
  const query = params.toString();
  const url = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
  if (url !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
    window.history.replaceState(null, '', url);
  }
}
