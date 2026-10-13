import { useTranslation } from '@lathephp/core';
import type { FilterControlProps } from '../types';
import { FilterFieldset, fieldClass } from './FilterFieldset';

/** Stored and shown values differ by the filter's scale; the URL keeps the stored one. */
function shown(stored: string | undefined, scale: number): string {
  return stored === undefined || stored === '' || scale === 1 ? (stored ?? '') : String(Number(stored) / scale);
}

function stored(typed: string, scale: number): string {
  return typed === '' || scale === 1 ? typed : String(Math.round(Number(typed) * scale));
}

/** `filter[total][min]=10`; either side may stay empty. */
export function RangeFilter({ filter, value, onChange }: FilterControlProps) {
  const { __ } = useTranslation();
  const scale = filter.scale ?? 1;
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
            step={scale === 1 ? undefined : 1 / scale}
            value={shown(sides[side], scale)}
            onChange={(event) => {
              onChange({ ...sides, [side]: stored(event.target.value, scale) });
            }}
          />
        ))}
      </div>
    </FilterFieldset>
  );
}
