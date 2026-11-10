import { router } from '@inertiajs/react';
import { formatNumber, type Translator } from '@lathephp/core';
import type { AvailableFilter } from './types';
import { type FilterValue, parseQuery } from './useIndexState';

/** A bar's filter change that waits before it is sent, so chips added together send one visit. */
export interface FilterVisits {
  /** Sends a filter change after the wait, replacing one still waiting. */
  defer: (send: () => void, wait: number) => void;
  /** Drops the filter change still waiting, so it cannot undo a visit made since. */
  cancel: () => void;
}

/** A wait of its own: a relation tab's change never drops another tab's, nor the index's. */
export function filterVisits(): FilterVisits {
  let pending: ReturnType<typeof setTimeout> | undefined;
  let stopWatching: (() => void) | undefined;

  function cancel() {
    clearTimeout(pending);
    stopWatching?.();
    stopWatching = undefined;
  }

  return {
    defer(send, wait) {
      cancel();
      // A visit to another page meanwhile drops it too, so the wait cannot pull the person back.
      stopWatching = router.on('start', (event) => {
        const { visit } = event.detail;

        if (!visit.prefetch && !visit.async && visit.url.pathname !== window.location.pathname) {
          cancel();
        }
      });
      pending = setTimeout(() => {
        cancel();
        send();
      }, wait);
    },
    cancel,
  };
}

/** The index page's wait, which its filter bar and filter panel share. */
export const indexVisits = filterVisits();

/** Sends a filter change after the wait, replacing one still waiting. */
export function deferFilterVisit(send: () => void, wait: number): void {
  indexVisits.defer(send, wait);
}

/** Drops the filter change still waiting, so it cannot undo a visit made since. */
export function cancelFilterVisit(): void {
  indexVisits.cancel();
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
    // A range's gt and lt sides leave out the value itself, so they read as > and <.
    const above = side(value.gt);
    const below = side(value.lt);

    if (low === undefined && high === undefined && (above !== undefined || below !== undefined)) {
      return above !== undefined ? `> ${above}` : `< ${below ?? ''}`;
    }

    // One value on both sides reads as that value, as the filter bar's parser labels it.
    return low !== undefined && high !== undefined ? (low === high ? low : `${low} – ${high}`) : low !== undefined ? `≥ ${low}` : `≤ ${high ?? ''}`;
  }

  return (Array.isArray(value) ? value : [value])
    // The server sends a record's id as a number; the URL holds text.
    .map((item) => (filter?.options as { label: string; value: unknown }[] | null | undefined)?.find((option) => String(option.value) === item)?.label ?? labels?.[item] ?? item)
    .join(', ');
}
