import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Select } from '../../components/ui/Select';
import type { FilterControlProps } from '../types';

export function SelectFilter({ filter, value, onChange }: FilterControlProps) {
  const { __ } = useTranslation();
  const id = useId();

  return (
    <div className="grid gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {filter.name}
      </label>
      <Select
        id={id}
        className="w-full"
        value={typeof value === 'string' ? value : ''}
        options={[{ value: '', label: __('Any') }, ...(filter.options ?? [])]}
        onChange={onChange}
      />
    </div>
  );
}
