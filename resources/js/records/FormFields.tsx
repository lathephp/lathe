import { useMemo, useState } from 'react';
import { FieldError } from '../components/FieldError';
import { passes } from '../fields/conditions';
import { FormField, stringValue } from '../fields/registry';
import { RepeaterErrorsContext } from '../fields/repeater/errors';
import type { FormFieldData } from '../fields/types';
import { cn } from '../lib/cn';
import { RecordLayout } from './RecordLayout';
import { useDependentFields } from './useDependentFields';
import type { useRecordForm } from './useRecordForm';

interface FormFieldsProps {
  uriKey: string;
  mode: 'create' | 'update' | 'replicate';
  id: string | number | null;
  record: ReturnType<typeof useRecordForm>;
  /** Prefixes the control ids, so a form inside a modal does not repeat the page's ids. */
  idPrefix?: string;
}

/**
 * The fields of a create or edit form, laid out as the Resource says. A field hides, requires
 * or locks by its conditions and the server's dependsOn patches, and shows its label, help
 * and first error.
 */
export function FormFields({ uriKey, mode, id, record, idPrefix = 'field' }: FormFieldsProps) {
  const byKey = new Map(record.payload.fields.map((field) => [field.key, field]));
  // The attributes the user typed in: a dependsOn patch never overwrites them.
  const [dirty, setDirty] = useState<ReadonlySet<string>>(() => new Set());
  const patches = useDependentFields({
    uriKey,
    mode,
    id,
    fields: record.payload.fields,
    values: record.values,
    dirty,
    apply: record.applyValues,
  });
  // One Set per failed submit, so the layout opens the marked tab once.
  const errorKeys = useMemo(() => new Set(Object.keys(record.errors).map((key) => key.split('.')[0] ?? key)), [record.errors]);

  return (
    <RepeaterErrorsContext value={record.errors}>
      <RecordLayout
        blocks={record.payload.layout}
        borderless={record.payload.borderless === true}
        errorKeys={errorKeys}
        renderField={(key, first) => {
          const field = byKey.get(key);

          const patch = patches[key] ?? {};

          if (field === undefined || patch.visible === false || (patch.visible !== true && field.visibleWhen != null && !passes(field.visibleWhen, record.values))) {
            return null;
          }

          const from = field.optionsFrom as { field: string; map: Record<string, unknown> } | null | undefined;
          const decided = {
            ...field,
            ...(from == null ? {} : { options: from.map[stringValue(record.values[from.field])] ?? [] }),
            ...(patch.options === undefined ? {} : { options: patch.options }),
            required: patch.required ?? (field.required === true || (field.requiredWhen != null && passes(field.requiredWhen, record.values))),
            readonly: patch.readonly ?? (field.readonly === true || (field.readonlyWhen != null && passes(field.readonlyWhen, record.values))),
          };

          return (
            <FormRow
              field={decided}
              wide={first}
              idPrefix={idPrefix}
              value={record.values[field.attribute]}
              values={record.values}
              error={record.errors[field.attribute]}
              onChange={(value) => {
                setDirty((current) => new Set(current).add(field.attribute));
                record.setValue(field.attribute, value);
              }}
            />
          );
        }}
      />
    </RepeaterErrorsContext>
  );
}

/**
 * One field of a form: its label above its control, help and first error. In a form panel's
 * two-column grid a wide row, a stacked one or a full-width one spans both columns.
 */
export function FormRow({ field, wide = false, idPrefix, value, values, error, onChange }: { field: FormFieldData; wide?: boolean; idPrefix: string; value: unknown; values: Record<string, unknown>; error: string | undefined; onChange: (value: unknown) => void }) {
  const id = `${idPrefix}-${field.attribute}`;
  const helpId = field.help === null ? undefined : `${id}-help`;
  const errorId = error === undefined ? undefined : `${id}-error`;
  const describedBy = [helpId, errorId].filter((part) => part !== undefined).join(' ') || undefined;

  return (
    <div className={cn('flex flex-col gap-2 px-4 py-3 text-sm', (wide || field.stacked || field.fullWidth) && 'lg:col-span-2')}>
      {field.compact ? null : (
        <label htmlFor={id} className="font-medium">
          {field.label}
          {field.required === true ? (
            <span aria-hidden="true" className="ms-0.5 text-destructive">
              *
            </span>
          ) : null}
        </label>
      )}
      <div className="min-w-0 space-y-1">
        <FormField
          field={field}
          value={value}
          values={values}
          onChange={onChange}
          id={id}
          describedBy={describedBy}
          invalid={error !== undefined}
          disabled={field.readonly === true}
        />
        {helpId === undefined ? null : (
          <p id={helpId} className="text-xs text-muted-foreground">
            {field.help}
          </p>
        )}
        {errorId === undefined ? null : <FieldError id={errorId} message={error} />}
      </div>
    </div>
  );
}
