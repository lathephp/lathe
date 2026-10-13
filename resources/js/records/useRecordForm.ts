import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useState } from 'react';
import { persistable, readDraft, useFormDraft } from './useFormDraft';
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
  const attributes = persistable(form.fields);
  const [saved] = useState(() => readDraft(attributes));
  const [values, setValues] = useState<Record<string, unknown>>(() => ({ ...initialValues(form.fields), ...saved }));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  /** The submit a 401 or 419 interrupted, replayed once the user signs in again. */
  const [reauth, setReauth] = useState<SubmitIntent | null>(null);
  /** A 409: someone saved the record since this form loaded. */
  const [conflict, setConflict] = useState<{ intent: SubmitIntent; updatedAt: string | null } | null>(null);
  const draft = useFormDraft(values, attributes, saved !== null);
  const base = `${apiPath}/resources/${resource.uriKey}`;
  const updating = mode === 'update';

  const setValue = (attribute: string, value: unknown) => {
    draft.touch();
    setValues((current) => ({ ...current, [attribute]: value }));
  };

  async function submit(intent: SubmitIntent = 'default', token: string | null | undefined = payload.updatedAt): Promise<void> {
      setBusy(true);
      setErrors({});

      try {
        const result = await request<WriteResult>(updating ? `${base}/${String(id)}` : base, {
          method: updating ? 'PUT' : 'POST',
          body: updating && token !== undefined ? { ...values, _updated_at: token } : values,
        });

        draft.clear();

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
        if (error instanceof ApiError && error.status === 409) {
          setConflict({ intent, updatedAt: (error.data as { updatedAt?: string | null } | null)?.updatedAt ?? null });
        } else if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
          setReauth(intent);
        } else if (error instanceof ApiError && error.status === 422) {
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

  return {
    payload,
    values,
    setValue,
    errors,
    busy,
    submit,
    reauth,
    draft: {
      restored: draft.restored,
      discard: () => {
        draft.clear();
        setValues(initialValues(payload.fields));
      },
    },
    /** Signed in again: replay the interrupted submit once. */
    resume: () => {
      const intent = reauth;
      setReauth(null);

      if (intent !== null) {
        void submit(intent);
      }
    },
    cancelReauth: () => {
      setReauth(null);
    },
    conflict: conflict !== null,
    /** Load the stored values again; the typed ones and the draft are dropped. */
    refresh: async () => {
      setConflict(null);
      draft.clear();
      const fresh = await request<FormPayload>(`${base}/${String(id)}/update-fields`);

      if (fresh !== null) {
        setPayload(fresh);
        setValues(initialValues(fresh.fields));
      }
    },
    /** Save the typed values over the other change, with the token the conflict returned. */
    override: () => {
      if (conflict === null) {
        return;
      }

      setConflict(null);
      setPayload((current) => ({ ...current, updatedAt: conflict.updatedAt }));
      void submit(conflict.intent, conflict.updatedAt);
    },
    cancelConflict: () => {
      setConflict(null);
    },
    /** Values the server set (dependsOn), merged without counting as typed. */
    applyValues: (changed: Record<string, unknown>) => {
      if (Object.keys(changed).length > 0) {
        setValues((current) => ({ ...current, ...changed }));
      }
    },
  };
}
