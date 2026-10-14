import { useContext } from 'react';
import { FieldError } from '../../components/FieldError';
import { RepeaterErrorsContext } from '../repeater/errors';
import { FormField, type FormFieldProps } from '../registry';
import type { FieldSchema } from '../types';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * The related Resource's fields of a hasOne or morphOne, inline in the parent form. Their
 * values post as one object under the field's attribute, so an error comes back under
 * `warranty.months`.
 */
export function HasOneForm({ field, value, onChange, id, disabled }: FormFieldProps) {
  const errors = useContext(RepeaterErrorsContext);
  const values = isRecord(value) ? value : {};
  const fields = Array.isArray(field.fields) ? (field.fields as FieldSchema[]) : [];

  return (
    <div id={id} className="grid gap-3 rounded-md border border-border p-3">
      {fields.map((sub) => {
        const subId = `${id}-${sub.attribute}`;
        const error = errors[`${field.attribute}.${sub.attribute}`];
        const cell = values[sub.attribute] ?? null;

        return (
          <div key={sub.attribute} className="min-w-0 space-y-1">
            <label htmlFor={subId} className="text-xs font-medium">
              {sub.label}
            </label>
            <FormField
              field={{ ...sub, value: cell }}
              value={cell}
              onChange={(next) => {
                onChange({ ...values, [sub.attribute]: next });
              }}
              id={subId}
              describedBy={error === undefined ? undefined : `${subId}-error`}
              invalid={error !== undefined}
              disabled={disabled || sub.readonly === true}
            />
            <FieldError id={`${subId}-error`} message={error} />
          </div>
        );
      })}
    </div>
  );
}
