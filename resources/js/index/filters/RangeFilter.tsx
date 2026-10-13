import { useTranslation } from '@lathephp/core';
import type { FilterControlProps } from '../types';
import { FilterFieldset, fieldClass } from './FilterFieldset';

/** `filter[total][min]=10`; either side may stay empty. */
export function RangeFilter({ filter, value, onChange }: FilterControlProps) {
  const { __ } = useTranslation();
  const sides = typeof value === 'object' && !Array.isArray(value) ? value : {};
  const labels = { min: __('Min'), max: __('Max') };

  return (
    <FilterFieldset legend={filter.name}>
      <div className="flex gap-2">
        {(['min', 'max'] as const).map((side) => (
          <input
            key={side}
            type="number"
            className={fieldClass}
            aria-label={labels[side]}
            placeholder={labels[side]}
            value={sides[side] ?? ''}
            onChange={(event) => {
              onChange({ ...sides, [side]: event.target.value });
            }}
          />
        ))}
      </div>
    </FilterFieldset>
  );
}
