import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import type { FilterControlProps } from '../types';
import { fieldClass } from './FilterFieldset';

export function SelectFilter({ filter, value, onChange }: FilterControlProps) {
  const { __ } = useTranslation();
  const id = useId();

  return (
    <div className="grid gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {filter.name}
      </label>
      <select
        id={id}
        name={filter.key}
        className={fieldClass}
        value={typeof value === 'string' ? value : ''}
        onChange={(event) => {
          onChange(event.target.value);
        }}
      >
        <option value="">{__('Any')}</option>
        {filter.options?.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  );
}
