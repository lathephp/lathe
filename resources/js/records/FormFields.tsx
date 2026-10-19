import { useMemo, useState } from 'react';
import { FieldError } from '../components/FieldError';
import { useTranslation } from '@lathephp/core';
import { labelOf, optionsOf } from '../fields/choice/options';
import { passes, type ConditionData } from '../fields/conditions';
import { FormField, stringValue } from '../fields/registry';
import { RepeaterErrorsContext } from '../fields/repeater/errors';
import { RepeaterFailuresContext } from '../fields/repeater/failures';
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
  const { __ } = useTranslation();
  const byKey = new Map(record.payload.fields.map((field) => [field.key, field]));

  // "Shown because Type is Company": the parent's label and the label of the value it shows for.
  function reasonFor(condition: ConditionData): string | null {
    const parent = 'field' in condition && (condition.op === 'eq' || condition.op === 'in') ? record.payload.fields.find((field) => field.attribute === condition.field) : undefined;

    if (parent === undefined || !('value' in condition)) {
      return null;
    }

    const values = Array.isArray(condition.value) ? condition.value : [condition.value];
    const shown = values.map((value) => labelOf(optionsOf(parent), stringValue(value))).join(' / ');

    return __('Shown because :field is :value', { field: parent.label, value: shown });
  }
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
  // The fields holding an error now, which the layout marks on their tabs.
  const errorKeys = useMemo(() => new Set(Object.keys(record.errors).map((key) => key.split('.')[0] ?? key)), [record.errors]);

  return (
    <RepeaterErrorsContext value={record.errors}>
      <RepeaterFailuresContext value={record.failures}>
        <RecordLayout
          blocks={record.payload.layout}
          borderless={record.payload.borderless === true}
          errorKeys={errorKeys}
          failures={record.failures}
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
                reason={field.visibleWhen == null ? undefined : reasonFor(field.visibleWhen)}
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
      </RepeaterFailuresContext>
    </RepeaterErrorsContext>
  );
}

/**
 * One field of a form: its label above its control, help and first error. In a form panel's
 * two-column grid a wide row, a stacked one or a full-width one spans both columns.
 */
export function FormRow({ field, wide = false, reason, idPrefix, value, values, error, onChange }: { field: FormFieldData; wide?: boolean; reason?: string | null; idPrefix: string; value: unknown; values: Record<string, unknown>; error: string | undefined; onChange: (value: unknown) => void }) {
  const { __ } = useTranslation();
  const id = `${idPrefix}-${field.attribute}`;
  const helpId = field.help === null ? undefined : `${id}-help`;
  const errorId = error === undefined ? undefined : `${id}-error`;
  const describedBy = [helpId, errorId].filter((part) => part !== undefined).join(' ') || undefined;

  return (
    <div
      data-readonly={field.readonly === true ? '' : undefined}
      data-dependent={reason === undefined ? undefined : ''}
      className={cn(
        // The label and its Optional mark share the first line; the control and its notes wrap below.
        'flex flex-wrap items-baseline gap-x-2 gap-y-1.5 text-sm',
        // A field shown by another sits under it on its own row, on the muted ground, with the reason.
        reason !== undefined && 'rounded-md bg-muted p-3',
        // Readonly reads as a settled value: muted ground, dashed edge. Any other disabled control is dimmed.
        field.readonly === true
          ? '[&_button]:border-dashed [&_button]:bg-muted [&_input]:border-dashed [&_input]:bg-muted [&_select]:border-dashed [&_select]:bg-muted [&_textarea]:border-dashed [&_textarea]:bg-muted'
          : '[&_:disabled]:opacity-50',
        (wide || reason !== undefined || field.stacked || field.fullWidth) && 'lg:col-span-2',
      )}
    >
      {field.compact ? null : (
        <>
          <label htmlFor={id} className="font-medium">
            {field.label}
          </label>
          {/* A required field goes bare; the others say so beside the label. */}
          {field.required === true || field.readonly === true ? null : <span className="text-xs text-muted-foreground">{__('Optional')}</span>}
        </>
      )}
      <div className="w-full min-w-0 space-y-1.5">
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
        {reason == null ? null : <p className="text-xs text-muted-foreground">{reason}</p>}
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
