import { __panel } from '@lathephp/core';
import { useSyncExternalStore } from 'react';
import { inputClass } from '../choice/options';
import type { FormFieldProps } from '../registry';
import { isBelongsToValue } from './BelongsToField';
import { BelongsToForm } from './BelongsToForm';
import { isMorphToValue } from './MorphToField';
import type { LookupRecord } from './useAssociatable';

interface MorphType {
  value: string;
  label: string;
}

/**
 * A type select, then a record picker for that type. A new type clears the record and keeps
 * the type as `{type, id: null}`, which the server reads as empty.
 */
export function MorphToForm(props: FormFieldProps & { record?: LookupRecord }) {
  const { field, value, onChange, id, describedBy, invalid, disabled, record } = props;
  const shared = useSyncExternalStore(__panel.sharedProps.subscribe, __panel.sharedProps.get);
  const current = isMorphToValue(value) ? value : null;
  const types = (field.types as MorphType[] | undefined) ?? [];
  const type = current?.type ?? (shared.mode !== 'update' && typeof field.defaultType === 'string' ? field.defaultType : '');
  const chosen = types.find((option) => option.value === type);

  // A field locked by the page shows its record and no picker.
  if (field.readonly === true) {
    return <span>{current?.display ?? '—'}</span>;
  }

  return (
    <div className="space-y-2">
      <select
        id={id}
        name={`${field.attribute}_type`}
        value={type}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(event) => {
          onChange(event.target.value === '' ? null : { type: event.target.value, id: null });
        }}
        className={inputClass}
      >
        <option value="">{'—'}</option>
        {types.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {chosen !== undefined && (
        <BelongsToForm
          // A new type starts a fresh picker: no search term and no options of the old type.
          key={chosen.value}
          {...props}
          id={`${id}-record`}
          field={{ ...field, singularLabel: chosen.label }}
          value={current !== null && current.id !== null ? { value: current.id, display: current.display ?? '' } : null}
          onChange={(picked) => {
            onChange(isBelongsToValue(picked) ? { type: chosen.value, id: picked.value, display: picked.display } : { type: chosen.value, id: null });
          }}
          type={chosen.value}
          record={record}
        />
      )}
    </div>
  );
}
