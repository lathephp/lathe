import { router, usePage } from '@inertiajs/react';
import { formatNumber, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import type { SharedProps } from '../types';
import type { AvailableFilter, IndexProps } from './types';
import { type FilterValue, type IndexUrlState, parseQuery } from './useIndexState';

// The filter panel's change still waiting to be sent; a chip or Reset that visits first drops it.
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
function chipValue(value: FilterValue, filter: AvailableFilter | undefined, labels: Record<string, string> | undefined): string {
  if (typeof value === 'object' && !Array.isArray(value)) {
    const scale = filter?.scale ?? 1;
    const side = (stored: string | undefined) =>
      stored === undefined || stored === '' ? undefined : filter?.component === 'range-filter' ? formatNumber(Number(stored) / scale) : stored;
    const low = side(value.min ?? value.from);
    const high = side(value.max ?? value.to);

    return low !== undefined && high !== undefined ? `${low} – ${high}` : low !== undefined ? `≥ ${low}` : `≤ ${high ?? ''}`;
  }

  return (Array.isArray(value) ? value : [value])
    .map((item) => filter?.options?.find((option) => option.value === item)?.label ?? labels?.[item] ?? item)
    .join(', ');
}

/** One removable chip per active filter, on its own row under the toolbar; nothing without a filter. */
export function FilterChips({ visit }: { visit: (changes: IndexUrlState) => void }) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { filters, staleFilters = [] } = page.props;
  const active = activeFilters(page.url, staleFilters);

  if (Object.keys(active).length === 0) {
    return null;
  }

  return (
    <div data-lathe-chips="" className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-2">
      {Object.entries(active).map(([key, value]) => {
        const filter = filters?.available.find((candidate) => candidate.key === key);
        const name = filter?.name ?? key;

        return (
          <span key={key} className="inline-flex h-7 items-center gap-1 rounded-full border border-border ps-3 pe-0.5 text-sm tabular-nums">
            <span>{`${name}: ${chipValue(value, filter, filters?.labels?.[key])}`}</span>
            <button
              type="button"
              aria-label={__('Remove :filter filter', { filter: name })}
              className="grid size-6 place-items-center rounded-full text-muted-foreground outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
              onClick={() => {
                cancelFilterVisit();
                visit({ filter: Object.fromEntries(Object.entries(active).filter(([other]) => other !== key)) });
              }}
            >
              <X aria-hidden="true" className="size-3" />
            </button>
          </span>
        );
      })}
      <button
        type="button"
        className="ms-1 rounded-sm text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
        onClick={() => {
          cancelFilterVisit();
          visit({ filter: {} });
        }}
      >
        {__('Clear filters')}
      </button>
    </div>
  );
}
