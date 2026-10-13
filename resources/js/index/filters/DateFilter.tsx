import { useTranslation } from '@lathephp/core';
import type { FilterControlProps } from '../types';
import { FilterFieldset, fieldClass } from './FilterFieldset';

/** `filter[placed][from]=2026-01-01`; either side may stay empty. */
export function DateFilter({ filter, value, onChange }: FilterControlProps) {
  const { __ } = useTranslation();
  const sides = typeof value === 'object' && !Array.isArray(value) ? value : {};
  const labels = { from: __('From'), to: __('To') };

  return (
    <FilterFieldset legend={filter.name}>
      <div className="flex gap-2">
        {(['from', 'to'] as const).map((side) => (
          <input
            key={side}
            type="date"
            className={fieldClass}
            aria-label={labels[side]}
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
