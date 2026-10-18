import { __panel, useTranslation } from '@lathephp/core';
import { type ComponentType, useId, useState } from 'react';
import { Button } from '../components/ui/Button';
import { DateFilter } from './filters/DateFilter';
import { Select } from '../components/ui/Select';
import { MultiSelectFilter } from './filters/MultiSelectFilter';
import { RangeFilter } from './filters/RangeFilter';
import { RemotePicker } from './filters/RemotePicker';
import { SelectFilter } from './filters/SelectFilter';
import type { AvailableFilter, FilterControlProps } from './types';
import type { FilterValue, IndexUrlState } from './useIndexState';

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
                visit({ trashed });
              }}
            />
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
