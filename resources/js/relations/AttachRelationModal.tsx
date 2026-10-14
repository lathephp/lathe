import { ApiError, request, toast, useQuery, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useId, useState } from 'react';
import { FieldError } from '../components/FieldError';
import { Modal } from '../components/Modal';
import { RelationPicker } from '../fields/relation/BelongsToForm';
import type { BelongsToValue } from '../fields/relation/BelongsToField';
import { FormField } from '../fields/registry';
import type { FormFieldData, RelationFieldSchema } from '../fields/types';
import { firstErrors, initialValues } from '../records/useRecordForm';
import { useRelationActions } from './useRelationActions';

/** The pivot form of an attach or an update, keyed by attribute. */
export function usePivotFields(url: string) {
  return useQuery({
    queryKey: ['pivot-fields', url],
    queryFn: () => request<{ fields: FormFieldData[] }>(url),
    staleTime: 0,
  });
}

/**
 * The messages of a failed write. The server keys pivot errors `pivot.<attribute>`, so they
 * land on the pivot field of that attribute; any other failure toasts.
 */
export function writeErrors(error: unknown, fallback: string): Record<string, string> {
  if (error instanceof ApiError && error.status === 422) {
    return Object.fromEntries(Object.entries(firstErrors(error.data)).map(([key, message]) => [key.replace(/^pivot\./, ''), message]));
  }

  const message = error instanceof ApiError && error.status === 403 ? (error.data as { message?: string } | null)?.message : undefined;
  toast.error(message ?? fallback);

  return {};
}

/** The pivot fields drawn by the form registry, each with its label and its error. */
export function PivotFields({ fields, values, errors, onChange }: { fields: FormFieldData[]; values: Record<string, unknown>; errors: Record<string, string>; onChange: (attribute: string, value: unknown) => void }) {
  const prefix = useId();

  return fields.map((field) => {
    const id = `${prefix}-${field.attribute}`;
    const error = errors[field.attribute];

    return (
      <div key={field.attribute} className="space-y-1 text-sm">
        <label htmlFor={id} className="font-medium">
          {field.label}
          {field.required === true ? (
            <span aria-hidden="true" className="ms-0.5 text-destructive">
              *
            </span>
          ) : null}
        </label>
        <FormField
          field={field}
          value={values[field.attribute]}
          values={values}
          onChange={(value) => {
            onChange(field.attribute, value);
          }}
          id={id}
          describedBy={error === undefined ? undefined : `${id}-error`}
          invalid={error !== undefined}
          disabled={field.readonly === true}
        />
        <FieldError id={`${id}-error`} message={error} />
      </div>
    );
  });
}

interface AttachRelationModalProps {
  parent: { uriKey: string; id: string | number };
  field: RelationFieldSchema;
  onClose: () => void;
}

/** Pick a record and fill the pivot form; "Attach & attach another" keeps the modal open for the next one. */
export function AttachRelationModal({ parent, field, onClose }: AttachRelationModalProps) {
  const { __ } = useTranslation();
  const titleId = useId();
  const pickerId = useId();
  const actions = useRelationActions(parent.uriKey, parent.id, field.relationship);
  const pivot = usePivotFields(`${actions.base}/pivot-fields`);
  const fields = pivot.data?.fields ?? [];
  const [picked, setPicked] = useState<BelongsToValue | null>(null);
  const [typed, setTyped] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const values = { ...initialValues(fields), ...typed };

  async function attach(another: boolean) {
    setBusy(true);
    setErrors({});

    try {
      await actions.attach(picked?.value ?? null, values);
      toast.success(__(':resource attached.', { resource: field.singularLabel }));

      if (another) {
        setPicked(null);
        setTyped({});
      } else {
        onClose();
      }
    } catch (error) {
      setErrors(writeErrors(error, __('Something went wrong. Please try again.')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal labelledBy={titleId} onClose={onClose}>
      <h2 id={titleId} className="text-base font-semibold">
        {__('Attach :resource', { resource: field.singularLabel })}
      </h2>
      <div className="space-y-1 text-sm">
        <label htmlFor={pickerId} className="font-medium">
          {field.singularLabel}
        </label>
        <RelationPicker
          field={{ ...field, attribute: 'related_id', searchable: true, value: picked }}
          value={picked}
          onChange={(value) => {
            setPicked(value as BelongsToValue | null);
          }}
          id={pickerId}
          describedBy={errors.related_id === undefined ? undefined : `${pickerId}-error`}
          invalid={errors.related_id !== undefined}
          disabled={false}
          endpoint={`${actions.base}/associatable`}
        />
        <FieldError id={`${pickerId}-error`} message={errors.related_id} />
      </div>
      <PivotFields
        fields={fields}
        values={values}
        errors={errors}
        onChange={(attribute, value) => {
          setTyped((current) => ({ ...current, [attribute]: value }));
        }}
      />
      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          {__('Cancel')}
        </Button>
        <Button
          variant="outline"
          disabled={busy}
          onClick={() => {
            void attach(true);
          }}
        >
          {__('Attach & attach another')}
        </Button>
        <Button
          disabled={busy}
          onClick={() => {
            void attach(false);
          }}
        >
          {__('Attach')}
        </Button>
      </div>
    </Modal>
  );
}
