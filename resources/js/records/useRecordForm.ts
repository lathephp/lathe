import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useCallback, useState } from 'react';
import type { FormFieldData, FormPayload, FormProps } from '../fields/types';

export type SubmitIntent = 'default' | 'another' | 'continue';

interface WriteResult {
  id: string | number;
  redirect: string;
}

/** The starting values of a form: each field's value, keyed by attribute. */
export function initialValues(fields: FormFieldData[]): Record<string, unknown> {
  return Object.fromEntries(fields.map((field) => [field.attribute, field.value]));
}

/** The first message of each attribute in a 422 body. */
export function firstErrors(data: unknown): Record<string, string> {
  const errors = (data as { errors?: Record<string, string[]> } | null)?.errors ?? {};

  return Object.fromEntries(
    Object.entries(errors).flatMap(([key, messages]) => (messages[0] === undefined ? [] : [[key, messages[0]]])),
  );
}

/**
 * The state and submit of a create or edit form. A failed submit keeps every typed value:
 * a 422 shows the messages under their fields, any other failure toasts with Retry.
 */
export function useRecordForm({ resource, mode, id, form }: Pick<FormProps, 'resource' | 'mode' | 'id' | 'form'>, onDone: (redirect: string) => void) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [payload, setPayload] = useState<FormPayload>(form);
  const [values, setValues] = useState<Record<string, unknown>>(() => initialValues(form.fields));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const base = `${apiPath}/resources/${resource.uriKey}`;
  const updating = mode === 'update';

  const setValue = useCallback((attribute: string, value: unknown) => {
    setValues((current) => ({ ...current, [attribute]: value }));
  }, []);

  async function submit(intent: SubmitIntent = 'default'): Promise<void> {
      setBusy(true);
      setErrors({});

      try {
        const result = await request<WriteResult>(updating ? `${base}/${String(id)}` : base, {
          method: updating ? 'PUT' : 'POST',
          body: updating && payload.updatedAt !== undefined ? { ...values, _updated_at: payload.updatedAt } : values,
        });

        if (intent === 'another' && !updating) {
          setValues(initialValues(form.fields));
          toast.success(__(':resource created.', { resource: resource.singularLabel }));
        } else if (intent === 'continue' && updating) {
          const fresh = await request<FormPayload>(`${base}/${String(id)}/update-fields`);

          if (fresh !== null) {
            setPayload(fresh);
            setValues(initialValues(fresh.fields));
          }

          toast.success(__(':resource updated.', { resource: resource.singularLabel }));
        } else if (result !== null) {
          onDone(result.redirect);
        }
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          setErrors(firstErrors(error.data));
          toast.error(__('Please fix the highlighted fields.'));
        } else {
          toast.error(__('Something went wrong. Please try again.'), {
            action: {
              label: __('Retry'),
              onClick: () => {
                void submit(intent);
              },
            },
          });
        }
      } finally {
        setBusy(false);
      }
  }

  return { payload, values, setValue, errors, busy, submit };
}
