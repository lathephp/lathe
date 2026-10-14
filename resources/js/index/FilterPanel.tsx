import { usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { ListFilter } from 'lucide-react';
import { type ComponentType, useId, useState } from 'react';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import type { SharedProps } from '../types';
import { DateFilter } from './filters/DateFilter';
import { fieldClass } from './filters/FilterFieldset';
import { MultiSelectFilter } from './filters/MultiSelectFilter';
import { RangeFilter } from './filters/RangeFilter';
import { RemotePicker } from './filters/RemotePicker';
import { SelectFilter } from './filters/SelectFilter';
import { IndexBanner } from './IndexBanner';
import type { AvailableFilter, FilterControlProps, IndexProps } from './types';
import { type FilterValue, type IndexUrlState, parseQuery } from './useIndexState';

// A boolean filter takes the list of checked options, the same shape as a multi-select.
const controls: Record<string, ComponentType<FilterControlProps>> = {
  'select-filter': SelectFilter,
  'multi-select-filter': MultiSelectFilter,
  'boolean-filter': MultiSelectFilter,
  'range-filter': RangeFilter,
  'date-filter': DateFilter,
};

interface FilterPanelProps {
  resource: string;
  /** The Resource's filters endpoint, `/lathe/api/resources/orders/filters`. */
  endpoint: string;
  available: AvailableFilter[];
  softDeletes: boolean;
  /** The URL state, without the filter keys the server dropped as stale. */
  state: IndexUrlState;
  visit: (changes: IndexUrlState) => void;
}

/**
 * The filters edit a draft that Apply writes to the URL. An immediate() filter and the trashed
 * choice visit on each change.
 */
export function FilterPanel({ resource, endpoint, available, softDeletes, state, visit }: FilterPanelProps) {
  const { __ } = useTranslation();
  const trashedId = useId();
  const applied = state.filter ?? {};
  const [draft, setDraft] = useState<Record<string, FilterValue>>(applied);

  return (
    <div className="grid gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                  setDraft({ ...draft, [filter.key]: value });

                  if (filter.immediate) {
                    visit({ filter: { ...applied, [filter.key]: value } });
                  }
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
            <select
              id={trashedId}
              name="trashed"
              className={fieldClass}
              value={state.trashed ?? ''}
              onChange={(event) => {
                visit({ trashed: event.target.value });
              }}
            >
              <option value="">{__('Without trashed')}</option>
              <option value="with">{__('With trashed')}</option>
              <option value="only">{__('Only trashed')}</option>
            </select>
          </div>
        )}
      </div>
      {available.length > 0 && (
        <div className="flex justify-end">
          <Button
            onClick={() => {
              visit({ filter: draft });
            }}
          >
            {__('Apply')}
          </Button>
        </div>
      )}
    </div>
  );
}

/** The toolbar's Filters button, its panel, and the banner for a link with removed filters. */
export function Filters({ visit }: { visit: (changes: IndexUrlState) => void }) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource, filters, staleFilters = [], basePath } = page.props;
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const state = parseQuery(page.url.split('?')[1] ?? '');
  const filter = Object.fromEntries(Object.entries(state.filter ?? {}).filter(([key]) => !staleFilters.includes(key)));
  const available = filters?.available ?? [];
  const softDeletes = resource.softDeletes;

  return (
    <>
      {(available.length > 0 || softDeletes) && (
        <Button
          variant="outline"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => {
            setOpen(!open);
          }}
        >
          <ListFilter aria-hidden="true" className="size-4" />
          {__('Filters')}
        </Button>
      )}
      {staleFilters.length > 0 && (
        <div className="basis-full">
          <IndexBanner
            message={__('This link is out of date')}
            onReset={() => {
              visit({ filter });
            }}
          />
        </div>
      )}
      {open && (
        <Card id={panelId} className="basis-full p-4">
          {/* A new URL starts a fresh draft from it. */}
          <FilterPanel
            key={page.url}
            resource={resource.uriKey}
            endpoint={`${basePath}/api/resources/${resource.uriKey}/filters`}
            available={available}
            softDeletes={softDeletes}
            state={{ ...state, filter }}
            visit={visit}
          />
        </Card>
      )}
    </>
  );
}
