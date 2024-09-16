// Common key codes for easy reference

export enum Key {
  Enter = 13,
  Esc = 27,
  Space = 32,
  PageUp = 33,
  PageDown = 34,
  LeftArrow = 37,
  UpArrow = 38,
  RightArrow = 39,
  DownArrow = 40,
  Delete = 46,
  Backspace = 8,
  Tab = 9,
  Comma = 188,
  Period = 190,
}

export const isKey = <E extends { keyCode: number }>(key: Key, e: E) => e.keyCode === key;

export function isShiftKeyPressed(e: JQuery.Event): boolean {
  return e.shiftKey === true && !e.ctrlKey && !e.altKey;
}
