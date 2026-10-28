import { router } from '@inertiajs/react';
import { formatNumber, type Translator } from '@lathephp/core';
import type { AvailableFilter } from './types';
import { type FilterValue, parseQuery } from './useIndexState';

// The filter change still waiting to be sent; a chip or Reset that visits first drops it.
let pending: ReturnType<typeof setTimeout> | undefined;
let stopWatching: (() => void) | undefined;

/** Sends a filter change after the wait, replacing one still waiting. */
export function deferFilterVisit(send: () => void, wait: number): void {
  cancelFilterVisit();
  // A visit to another page meanwhile drops it too, so the wait cannot pull the person back.
  stopWatching = router.on('start', (event) => {
    const { visit } = event.detail;

    if (!visit.prefetch && !visit.async && visit.url.pathname !== window.location.pathname) {
      cancelFilterVisit();
    }
  });
  pending = setTimeout(() => {
    cancelFilterVisit();
    send();
  }, wait);
}

/** Drops the filter change still waiting, so it cannot undo a visit made since. */
export function cancelFilterVisit(): void {
  clearTimeout(pending);
  stopWatching?.();
  stopWatching = undefined;
}

/** The URL's filters without the keys the server dropped as stale. */
export function activeFilters(url: string, staleFilters: string[]): Record<string, FilterValue> {
  const filter = parseQuery(url.split('?')[1] ?? '').filter ?? {};

  return Object.fromEntries(Object.entries(filter).filter(([key]) => !staleFilters.includes(key)));
}

/** What a chip says after the filter's name: option labels joined, or the two sides of a range or a date. */
export function chipValue(value: FilterValue, filter: AvailableFilter | undefined, labels: Record<string, string> | undefined, __: Translator): string {
  // A boolean filter's flags: Yes or No for a lone option, else the options turned on.
  if (typeof value === 'object' && !Array.isArray(value) && filter?.component === 'boolean-filter') {
    const on = (flag: string | undefined) => flag === '1' || flag === 'true';
    const [only] = filter.options ?? [];

    return filter.options?.length === 1 && only !== undefined
      ? on(value[only.value]) ? __('Yes') : __('No')
      : (filter.options ?? []).filter((option) => on(value[option.value])).map((option) => option.label).join(', ');
  }

  if (typeof value === 'object' && !Array.isArray(value)) {
    const scale = filter?.scale ?? 1;
    const side = (stored: string | undefined) =>
      stored === undefined || stored === '' ? undefined : filter?.component === 'range-filter' ? formatNumber(Number(stored) / scale) : stored;
    const low = side(value.min ?? value.from);
    const high = side(value.max ?? value.to);

    // One value on both sides reads as that value, as the filter bar's parser labels it.
    return low !== undefined && high !== undefined ? (low === high ? low : `${low} – ${high}`) : low !== undefined ? `≥ ${low}` : `≤ ${high ?? ''}`;
  }

  return (Array.isArray(value) ? value : [value])
    // The server sends a record's id as a number; the URL holds text.
    .map((item) => (filter?.options as { label: string; value: unknown }[] | null | undefined)?.find((option) => String(option.value) === item)?.label ?? labels?.[item] ?? item)
    .join(', ');
}
