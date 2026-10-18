import { formatDate } from '@lathephp/core';

export const pad = (part: number) => String(part).padStart(2, '0');

/**
 * A typed date, "2026-10-17", "Oct 17, 2026" or "17 oct. 2026" in the user's locale, as Y-m-d, with
 * "THH:mm" when a 24-hour time such as "23:12" follows. Null when it names no real day or time.
 */
export function parseDate(text: string): string | null {
  const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ,]+(\d{1,2}):(\d{2}))?$/.exec(text.trim());
  const words = /^(?:(\p{L}+)\.? (\d{1,2})|(\d{1,2})\.? (\p{L}+)\.?),? (\d{4})(?:,? (\d{1,2}):(\d{2}))?$/u.exec(text.trim());
  let parts: (string | undefined)[];

  if (iso) {
    parts = iso.slice(1);
  } else if (words) {
    // The locale's short then long month names, so the index modulo 12 is the month.
    const names = (['short', 'long'] as const).flatMap((month) =>
      Array.from({ length: 12 }, (_, index) => formatDate(`2026-${pad(index + 1)}-01`, { dateStyle: undefined, month }).toLowerCase().replace('.', '')),
    );
    const month = names.indexOf((words[1] ?? words[4] ?? '').toLowerCase());

    if (month < 0) return null;
    parts = [words[5], String((month % 12) + 1), words[2] ?? words[3], words[6], words[7]];
  } else {
    return null;
  }

  const [year = 0, month = 0, day = 0, hour = 0, minute = 0] = parts.map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));

  if (date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day || hour > 23 || minute > 59) {
    return null;
  }

  const ymd = `${String(year)}-${pad(month)}-${pad(day)}`;

  return parts[3] === undefined ? ymd : `${ymd}T${pad(hour)}:${pad(minute)}`;
}
