import { Checkbox } from '@lathephp/ui';
import type { FilterControlProps } from '../types';
import { FilterFieldset } from './FilterFieldset';

/** One checkbox per option; the value is the list of checked options, `filter[tag][]=a`. */
export function MultiSelectFilter({ filter, value, onChange }: FilterControlProps) {
  const checked = Array.isArray(value) ? value : [];

  return (
    <FilterFieldset legend={filter.name}>
      {filter.options?.map((option) => (
        <label key={option.value} className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={checked.includes(option.value)}
            onCheckedChange={(isChecked) => {
              onChange(isChecked ? [...checked, option.value] : checked.filter((item) => item !== option.value));
            }}
          />
          {option.label}
        </label>
      ))}
    </FilterFieldset>
  );
}
