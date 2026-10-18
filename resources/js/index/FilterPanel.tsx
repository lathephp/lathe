import { __panel, useTranslation } from '@lathephp/core';
import { type ComponentType, useId, useRef, useState } from 'react';
import { Select } from '../components/ui/Select';
import { DateFilter } from './filters/DateFilter';
import { MultiSelectFilter } from './filters/MultiSelectFilter';
import { RangeFilter } from './filters/RangeFilter';
import { RemotePicker } from './filters/RemotePicker';
import { SelectFilter } from './filters/SelectFilter';
import type { AvailableFilter, FilterControlProps } from './types';
import { type FilterValue, type IndexUrlState, toQuery } from './useIndexState';

// A boolean filter takes the list of checked options, the same shape as a multi-select.
const controls: Record<string, ComponentType<FilterControlProps>> = {
  'select-filter': SelectFilter,
  'multi-select-filter': MultiSelectFilter,
  'boolean-filter': MultiSelectFilter,
  'range-filter': RangeFilter,
  'date-filter': DateFilter,
};

// Long enough to wait out a number being typed; one visit carries every change made meanwhile.
const debounceMs = 300;

/** The filters as the URL writes them, empty values dropped, to tell one draft from another. */
function filterKey(filter: Record<string, FilterValue>): string {
  return JSON.stringify((toQuery({ filter }, { perPage: 0 }) as IndexUrlState).filter ?? {});
}

interface FilterPanelProps {
  resource: string;
  /** The Resource's filters endpoint, `/lathe/api/resources/orders/filters`. */
  endpoint: string;
  available: AvailableFilter[];
  softDeletes: boolean;
  /** The URL state, without the filter keys the server dropped as stale. */
  state: IndexUrlState;
  /** The page URL; a new one the panel's own visit did not make starts the draft again. */
  url?: string;
  visit: (changes: IndexUrlState) => void;
}

/**
 * Each change edits a draft that one visit sends 300 ms after the last change. An immediate()
 * filter and the trashed choice visit at once, with the draft as it stands.
 */
export function FilterPanel({ resource, endpoint, available, softDeletes, state, url, visit }: FilterPanelProps) {
  const { __ } = useTranslation();
  const trashedId = useId();
  const applied = state.filter ?? {};
  const [draft, setDraft] = useState<Record<string, FilterValue>>(applied);
  const [seenUrl, setSeenUrl] = useState(url);
  const [sent, setSent] = useState<string | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  // The panel's own visit keeps the draft, so a range typed on while it was out survives it.
  // Any other new URL, such as a removed chip or a clicked tab, starts the draft again from it.
  if (url !== seenUrl) {
    setSeenUrl(url);

    if (filterKey(applied) !== sent) {
      setDraft(applied);
    }
  }

  function send(changes: IndexUrlState, filter: Record<string, FilterValue>) {
    clearTimeout(timer.current);
    setSent(filterKey(filter));
    visit({ ...changes, filter });
  }

  function change(filter: AvailableFilter, value: FilterValue) {
    const next = { ...draft, [filter.key]: value };
    setDraft(next);

    if (filter.immediate) {
      send({}, next);

      return;
    }

    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      send({}, next);
    }, debounceMs);
  }

  // Columns follow the panel's own width: one in the index's popover, more inline in a relation panel.
  return (
    <div className="@container">
      <div className="grid gap-4 @xl:grid-cols-2 @4xl:grid-cols-3">
        {available.map((filter) => {
          // A key no built-in control draws is one a plugin registered with core's filter().
          const Control = filter.remote ? RemotePicker : (controls[filter.component] ?? (__panel.filterComponent(filter.component) as ComponentType<FilterControlProps> | undefined));

          return (
            Control !== undefined && (
              <Control
                key={filter.key}
                filter={filter}
                value={draft[filter.key]}
                endpoint={endpoint}
                resource={resource}
                onChange={(value) => {
                  change(filter, value);
                }}
              />
            )
          );
        })}
        {softDeletes && (
          <div className="grid gap-1.5">
            <label htmlFor={trashedId} className="text-sm font-medium">
              {__('Trashed')}
            </label>
            <Select
              id={trashedId}
              className="w-full"
              value={state.trashed ?? ''}
              options={[
                { value: '', label: __('Without trashed') },
                { value: 'with', label: __('With trashed') },
                { value: 'only', label: __('Only trashed') },
              ]}
              onChange={(trashed) => {
                send({ trashed }, draft);
              }}
            />
          </div>
        )}
      </div>
    </div>
  );
}
