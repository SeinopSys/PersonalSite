import { roundTo } from '../utils';

export const LRC_TS_DECIMALS = 3;

export function floatToPercent(float: number): string {
  return `${roundTo(float, 5) * 100}%`;
}

export function clearFocus(): void {
  const { activeElement } = document;
  if (activeElement && typeof (activeElement as HTMLElement).blur === 'function') (activeElement as HTMLElement).blur();
}

export function binarySearch<V>(arr: V[], compare: (value: V) => number): number { // binary search, with custom compare function
  let l = 0;
  let r = arr.length - 1;
  while (l <= r) {
    // eslint-disable-next-line no-bitwise
    const m = l + ((r - l) >> 1);
    const comp = compare(arr[m]);
    // arr[m] comes before the element
    if (comp < 0) l = m + 1;
    // arr[m] comes after the element
    else if (comp > 0) r = m - 1;
    // this[m] equals the element
    else return m;
  }
  return l - 1; // return the index of the next left item
  // usually you would just return -1 in case nothing is found
}

export const LRC_META_TAGS = {
  ar: 'artist',
  ti: 'title',
  al: 'album',
  au: 'lyrics_author',
  length: 'length',
  by: 'file_author',
  offset: 'offset',
  re: 'created_with',
  ve: 'version',
} as const;
export const LRC_TS_REGEX = /\[([\d:.]+)]/g;
export const LRC_META_REGEX = new RegExp(`^\\[(${Object.keys(LRC_META_TAGS).join('|')}):([^\\]]+)]$`);
export type LRCMetadataKeys = keyof typeof LRC_META_TAGS;
export type LRCMetadata = Partial<Record<LRCMetadataKeys, string>> & { [k: string]: string };
